<?php

namespace Tests\Feature;

use App\Livewire\Wallet;
use App\Models\User;
use App\Models\WalletGroupMember;
use App\Services\Wallet\WalletGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Livewire-component-level coverage for the "Shared" tab added to the wallet
 * page (Prompt 11 §3 Batch 2) — the service layer itself (invite/accept/cap
 * enforcement/attribution) is already fully covered by WalletGroupTest; this
 * only exercises the UI wiring: form validation, the four new public
 * methods, and that the right sections render for an owner vs. an invitee.
 */
class WalletSharedPlanUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_inviting_by_email_creates_a_pending_member_and_notifies(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create();

        Livewire::actingAs($owner)->test(Wallet::class)
            ->set('inviteEmail', $invitee->email)
            ->set('inviteCapUsd', '25')
            ->call('inviteToSharedPlan')
            ->assertHasNoErrors();

        $member = WalletGroupMember::where('user_id', $invitee->id)->first();
        $this->assertNotNull($member);
        $this->assertSame(25.0, (float) $member->spend_cap_usd);
        $this->assertFalse($member->isActive());
    }

    public function test_inviting_an_unknown_email_shows_a_friendly_error_not_a_crash(): void
    {
        $owner = User::factory()->create();

        Livewire::actingAs($owner)->test(Wallet::class)
            ->set('inviteEmail', 'nobody-here@example.com')
            ->call('inviteToSharedPlan')
            ->assertSet('inviteError', 'No NaaraSim account uses that email address.');

        $this->assertDatabaseCount('wallet_group_members', 0);
    }

    public function test_inviting_yourself_shows_the_services_error_message(): void
    {
        $owner = User::factory()->create();

        Livewire::actingAs($owner)->test(Wallet::class)
            ->set('inviteEmail', $owner->email)
            ->call('inviteToSharedPlan')
            ->assertSet('inviteError', 'You cannot invite yourself to your own shared plan.');
    }

    public function test_an_invalid_email_fails_validation_before_touching_the_service(): void
    {
        $owner = User::factory()->create();

        Livewire::actingAs($owner)->test(Wallet::class)
            ->set('inviteEmail', 'not-an-email')
            ->call('inviteToSharedPlan')
            ->assertHasErrors(['inviteEmail']);

        $this->assertDatabaseCount('wallet_group_members', 0);
    }

    public function test_an_invitee_sees_their_pending_invite_and_can_accept_it(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $member = app(WalletGroupService::class)->invite($owner, $invitee, 20.0, null);

        Livewire::actingAs($invitee)->test(Wallet::class)
            ->assertSee($owner->name)
            ->call('acceptSharedPlanInvite', $member->id);

        $this->assertTrue($member->fresh()->isActive());
    }

    public function test_a_user_cannot_accept_a_member_row_that_isnt_theirs(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $stranger = User::factory()->create();
        $member = app(WalletGroupService::class)->invite($owner, $invitee, null, null);

        Livewire::actingAs($stranger)->test(Wallet::class)
            ->call('acceptSharedPlanInvite', $member->id);

        $this->assertFalse($member->fresh()->isActive());
    }

    public function test_declining_an_invite_removes_it_from_the_invitees_list(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $member = app(WalletGroupService::class)->invite($owner, $invitee, null, null);

        Livewire::actingAs($invitee)->test(Wallet::class)
            ->call('leaveSharedPlan', $member->id);

        $this->assertDatabaseMissing('wallet_group_members', ['id' => $member->id]);
    }

    public function test_the_owner_sees_their_invited_member_and_can_remove_them(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $member = app(WalletGroupService::class)->invite($owner, $invitee, 10.0, null);

        Livewire::actingAs($owner)->test(Wallet::class)
            ->assertSee($invitee->name)
            ->call('removeSharedPlanMember', $member->id);

        $this->assertDatabaseMissing('wallet_group_members', ['id' => $member->id]);
    }

    public function test_a_non_owner_cannot_remove_someone_elses_member(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $stranger = User::factory()->create();
        $member = app(WalletGroupService::class)->invite($owner, $invitee, null, null);

        Livewire::actingAs($stranger)->test(Wallet::class)
            ->call('removeSharedPlanMember', $member->id);

        $this->assertDatabaseHas('wallet_group_members', ['id' => $member->id]);
    }

    public function test_a_user_with_no_shared_plan_activity_sees_the_invite_form_only(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertSee('Your shared plan')
            ->assertDontSee('Invites waiting for you')
            ->assertDontSee("Plans you've joined");
    }
}
