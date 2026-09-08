<?php

namespace Tests\Feature;

use App\Livewire\Admin\MarketingCopyStudio;
use App\Models\PageSection;
use App\Models\Setting;
use App\Models\User;
use App\Services\AI\AnthropicClient;
use App\Services\Marketing\MarketingCopywriter;
use App\Support\BrandSettings;
use App\Support\CopyFields;
use App\Support\MarketingBrief;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Claude-assisted marketing copy populator: it rewrites only human copy
 * (never links/assets/structure), trains from the saved brand brief, and applies
 * a chosen variation into the section draft.
 */
class MarketingCopyStudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /** A fake copywriter AI that echoes the copy back UPPERCASED, so we can prove
     *  structure is preserved and only copy changes — no network. */
    private function fakeAi(): void
    {
        $fake = new class extends AnthropicClient
        {
            public function enabled(): bool
            {
                return true;
            }

            public function completeJson(string $system, array $messages, int $maxTokens = 4096): array
            {
                // Pull the {path: text} JSON out of the user message and uppercase it.
                $content = $messages[0]['content'] ?? '';
                preg_match('/\{.*\}/s', substr($content, (int) strpos($content, 'current text):')), $m);
                $copy = json_decode($m[0] ?? '{}', true) ?: [];
                $variation = array_map(fn ($t) => strtoupper((string) $t), $copy);

                return ['variations' => [$variation, $variation]];
            }
        };
        $this->app->instance(AnthropicClient::class, $fake);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function heroSection(): PageSection
    {
        return PageSection::create([
            'page_key' => 'home', 'type' => 'two_column', 'sort_order' => 0, 'is_active' => true,
            'config' => [
                'headline' => 'Old headline', 'body' => 'Old body copy.',
                'image' => '/img/hero.png', 'cta_label' => 'Buy now', 'cta_target' => '/checkout', 'bg' => 'tint',
            ],
        ]);
    }

    public function test_copy_extraction_skips_links_assets_and_enums(): void
    {
        $copy = CopyFields::extract([
            'headline' => 'Hello', 'image' => '/x.png', 'cta_target' => '/go',
            'bg' => 'tint', 'cta_label' => 'Go', 'nested' => ['title' => 'Deep', 'src' => 'https://a/b.jpg'],
        ]);

        $this->assertSame(['headline' => 'Hello', 'cta_label' => 'Go', 'nested.title' => 'Deep'], $copy);
    }

    public function test_brief_defaults_brand_to_the_white_label_word(): void
    {
        Setting::setValue('brand.word', 'Acme', 'brand');
        BrandSettings::flush();

        $this->assertSame('Acme', MarketingBrief::get()['brand']);
    }

    public function test_copywriter_rewrites_only_copy_and_keeps_structure(): void
    {
        $this->fakeAi();
        $section = $this->heroSection();

        $variations = app(MarketingCopywriter::class)->sectionVariations($section->type, (array) $section->config, 2);

        $this->assertCount(2, $variations);
        $this->assertSame('OLD HEADLINE', $variations[0]['headline']);   // copy rewritten
        $this->assertSame('OLD BODY COPY.', $variations[0]['body']);
        $this->assertSame('/img/hero.png', $variations[0]['image']);     // asset untouched
        $this->assertSame('/checkout', $variations[0]['cta_target']);    // link untouched
        $this->assertSame('tint', $variations[0]['bg']);                 // enum untouched
    }

    public function test_studio_is_admin_only(): void
    {
        Livewire::actingAs(User::factory()->create())->test(MarketingCopyStudio::class)->assertStatus(403);
        Livewire::actingAs($this->admin())->test(MarketingCopyStudio::class)->assertOk()->assertSee('Copy Studio');
    }

    public function test_generate_then_apply_writes_the_draft(): void
    {
        $this->fakeAi();
        $section = $this->heroSection();

        Livewire::actingAs($this->admin())->test(MarketingCopyStudio::class)
            ->call('generate', $section->id)
            ->call('apply', $section->id, 0)
            ->assertDispatched('nx-toast');

        $this->assertSame('OLD HEADLINE', $section->fresh()->config['headline']);
        $this->assertSame('/img/hero.png', $section->fresh()->config['image']); // still safe
    }
}
