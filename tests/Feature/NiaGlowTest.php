<?php

namespace Tests\Feature;

use App\Livewire\SupportChat;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\Support\Contracts\ChatModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeChatModel;
use Tests\TestCase;

/**
 * BUILD-3 §5: Nia glow + 3-phase paced reveal live on SupportChat only. The
 * server still persists the reply synchronously (history/tests unaffected); it
 * marks the newest reply so the client animates it, and the glow wraps only the
 * Nia input + AI/typing bubbles.
 */
class NiaGlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reply_is_marked_as_the_message_to_stream(): void
    {
        $this->app->instance(ChatModel::class, new FakeChatModel([FakeChatModel::text('Here is how to install your eSIM.')]));

        $component = Livewire::actingAs(User::factory()->create())->test(SupportChat::class)
            ->set('draft', 'How do I install?')
            ->call('send')
            ->assertSee('Here is how to install your eSIM.');

        // The newest assistant message is flagged for the paced client reveal.
        $streamId = $component->get('streamMessageId');
        $this->assertNotNull($streamId);
        $lastAssistant = SupportMessage::where('role', 'assistant')->latest('id')->first();
        $this->assertSame($lastAssistant->id, $streamId);
    }

    public function test_the_glow_wraps_the_nia_input_and_ai_bubbles_only(): void
    {
        $this->app->instance(ChatModel::class, new FakeChatModel([FakeChatModel::text('Sure, happy to help.')]));

        $html = Livewire::actingAs(User::factory()->create())->test(SupportChat::class)
            ->set('draft', 'hi')
            ->call('send')
            ->html();

        // Glow wrapper on the input + glow class on the AI bubble + the paced
        // typewriter + the store-driven typing indicator are all wired.
        $this->assertStringContainsString('nia-glow', $html);
        $this->assertStringContainsString('niaBubble', $html);
        $this->assertStringContainsString("\$store.nia.phase === 'typing'", $html);
    }

    public function test_the_brand_glow_colours_are_defined(): void
    {
        // The three colours pulled from the Naara brand gradient (§5).
        $css = file_get_contents(resource_path('css/nia-glow.css'));
        $this->assertStringContainsString('--nia-glow-1: #0A6E6E', $css); // Deep Teal
        $this->assertStringContainsString('--nia-glow-2: #D4A017', $css); // Warm Gold
        $this->assertStringContainsString('--nia-glow-3: #2dd4bf', $css); // Bright Teal
    }
}
