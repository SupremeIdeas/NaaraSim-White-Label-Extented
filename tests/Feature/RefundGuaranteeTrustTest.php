<?php

namespace Tests\Feature;

use App\Jobs\PollSmsOtpJob;
use App\Livewire\GetNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prompt 10 §1 — the refund guarantee, surfaced truthfully in three places:
 * the Naara Verify checkout modal, PricingPage, and the FAQ. Every place
 * pulls the timeout from PollSmsOtpJob::TIMEOUT_MINUTES rather than a
 * hardcoded guess, so they can never silently drift out of sync with what
 * the job actually does.
 */
class RefundGuaranteeTrustTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_verify_modal_states_the_real_timeout(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(GetNumber::class, ['modal' => 'verify'])
            ->assertSee(PollSmsOtpJob::TIMEOUT_MINUTES.' minutes')
            ->assertSee('automatic refund');
    }

    public function test_the_pricing_page_states_the_real_timeout_and_links_the_policy(): void
    {
        $this->get('/pricing')
            ->assertOk()
            ->assertSee(PollSmsOtpJob::TIMEOUT_MINUTES.' minutes')
            ->assertSee(route('refund-policy'), false);
    }

    public function test_the_faq_states_the_real_timeout_and_links_the_policy(): void
    {
        $this->get('/faq')
            ->assertOk()
            ->assertSee(PollSmsOtpJob::TIMEOUT_MINUTES.' minutes')
            ->assertSee(route('refund-policy'), false);
    }

    public function test_the_refund_policy_page_states_the_real_timeout(): void
    {
        $this->get('/refund-policy')
            ->assertOk()
            ->assertSee(PollSmsOtpJob::TIMEOUT_MINUTES.' minutes')
            ->assertDontSee('{otp_timeout_minutes}');
    }

    /** The same policy content is reachable via the generic /legal/{slug} route too. */
    public function test_the_legal_refund_slug_route_also_resolves(): void
    {
        $this->get('/legal/refund')
            ->assertOk()
            ->assertSee(PollSmsOtpJob::TIMEOUT_MINUTES.' minutes');
    }
}
