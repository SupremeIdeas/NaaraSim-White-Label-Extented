<?php

namespace Tests\Feature;

use App\Livewire\Admin\Posts;
use App\Models\User;
use App\Services\AI\AnthropicClient;
use App\Services\Blog\BlogArticleAssistant;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Claude-assisted blog authoring. Uses a fake AnthropicClient so no real API is
 * hit. Verifies topic suggestion, draft generation into the form, the plain
 * body format (no HTML — the blog renderer escapes), and graceful behaviour when
 * the key is absent.
 */
class BlogAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    private function fakeAi(bool $enabled = true): void
    {
        app()->instance(AnthropicClient::class, new class($enabled) extends AnthropicClient
        {
            public function __construct(private bool $on) {}

            public function enabled(): bool
            {
                return $this->on;
            }

            public function completeJson(string $system, array $messages, int $maxTokens = 4096): array
            {
                return [
                    'title' => 'eSIM vs Roaming in Nigeria',
                    'category' => 'Guides',
                    'angle' => 'Compare costs; keyword "eSIM Nigeria".',
                    'rationale' => 'No post covers Nigeria specifically.',
                    'body' => "## Intro\n\nStay connected.\n\n- Point one\n- Point two",
                    'excerpt' => 'A quick comparison.',
                    'meta_title' => 'eSIM vs Roaming Nigeria',
                    'meta_description' => 'Which is cheaper in Nigeria?',
                ];
            }

            public function complete(string $system, array $messages, int $maxTokens = 4096, float $temperature = 0.2): string
            {
                return 'A teal-and-gold aerial of Lagos at dusk, glowing connection lines, no text.';
            }
        });
    }

    public function test_service_suggests_a_topic(): void
    {
        $this->fakeAi();
        $topic = app(BlogArticleAssistant::class)->suggestTopic();

        $this->assertSame('eSIM vs Roaming in Nigeria', $topic['title']);
        $this->assertSame('Guides', $topic['category']);
    }

    public function test_generated_body_is_plain_light_markup_not_html(): void
    {
        $this->fakeAi();
        $draft = app(BlogArticleAssistant::class)->generateDraft('T', 'Guides', 'angle');

        $this->assertStringContainsString('## Intro', $draft['body']);
        $this->assertStringNotContainsString('<', $draft['body']); // renderer escapes HTML
        $this->assertNotEmpty($draft['meta_title']);
    }

    public function test_admin_can_suggest_and_generate_through_the_ui(): void
    {
        $this->fakeAi();

        Livewire::actingAs($this->admin())->test(Posts::class)
            ->assertSet('suggestion', null)
            ->call('suggestTopic')
            ->assertSet('suggestion.title', 'eSIM vs Roaming in Nigeria')
            ->call('useSuggestion')
            ->assertSet('showForm', true)
            ->assertSet('title', 'eSIM vs Roaming in Nigeria')
            ->call('generateDraft')
            ->assertSet('excerpt', 'A quick comparison.')
            ->assertSet('body', "## Intro\n\nStay connected.\n\n- Point one\n- Point two");
    }

    public function test_image_prompt_is_produced_for_copying(): void
    {
        $this->fakeAi();

        Livewire::actingAs($this->admin())->test(Posts::class)
            ->set('title', 'eSIM Nigeria')->set('body', 'Some body')
            ->call('getImagePrompt')
            ->assertSet('aiImagePrompt', 'A teal-and-gold aerial of Lagos at dusk, glowing connection lines, no text.');
    }

    public function test_assist_is_hidden_without_a_key(): void
    {
        $this->fakeAi(enabled: false);

        Livewire::actingAs($this->admin())->test(Posts::class)->assertSet('suggestion', null);
        $this->assertFalse(app(BlogArticleAssistant::class)->enabled());
    }
}
