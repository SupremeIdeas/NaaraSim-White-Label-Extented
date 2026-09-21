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

    /**
     * Frontend-UX-fix blueprint Phase B — root-caused via Playwright: the page
     * used to size itself with a hardcoded `h-[calc(100vh-9rem)]`, which never
     * accounted for the optional "confirm your email" banner. Whenever that
     * banner showed, the input row — sitting at the bottom of that fixed-height
     * flex column — rendered PARTLY BEHIND the floating mobile bottom nav
     * instead of above it, which is exactly what read as a clipped/"boxed"
     * pill rather than the full rounded shape. Fixed by measuring the real
     * available space at runtime (niaChatLayout in resources/js/nia-chat.js)
     * instead of guessing a static number.
     */
    public function test_the_page_measures_its_own_height_instead_of_guessing_a_static_number(): void
    {
        $html = Livewire::actingAs(User::factory()->create())->test(SupportChat::class)->html();

        $this->assertStringContainsString('x-data="niaChatLayout"', $html);
        $this->assertStringNotContainsString('h-[calc(100vh-9rem)]', $html);

        $js = file_get_contents(resource_path('js/nia-chat.js'));
        $this->assertStringContainsString("A.data('niaChatLayout'", $js);
    }

    public function test_the_input_has_appearance_none_and_a_subtle_border(): void
    {
        $html = Livewire::actingAs(User::factory()->create())->test(SupportChat::class)->html();

        $this->assertStringContainsString('appearance-none', $html);
        $this->assertStringContainsString('border-slate-200/70', $html);
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
