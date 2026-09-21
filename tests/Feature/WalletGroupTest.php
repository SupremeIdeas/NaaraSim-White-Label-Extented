<?php

namespace Tests\Feature;

use App\Exceptions\SpendCapExceededException;
use App\Models\User;
use App\Models\WalletGroupMember;
use App\Models\WalletTransaction;
use App\Notifications\WalletGroupInvitedNotification;
use App\Services\Wallet\WalletGroupService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Prompt 11 §3 — the shared-wallet / family plan primitive. A group's
 * spending always debits the OWNER's real UserWallet through WalletService's
 * completely unmodified public methods; this only tests the additive layer
 * on top (invite/accept/cap-enforcement/attribution) and that a user with no
 * group membership sees zero change to their existing single-wallet flow.
 */
class WalletGroupTest extends TestCase
{
    use RefreshDatabase;

    private function walletGroups(): WalletGroupService
    {
        return app(WalletGroupService::class);
    }

    private function wallet(): WalletService
    {
        return app(WalletService::class);
    }

    // --- invite / accept / decline -----------------------------------------

    public function test_inviting_creates_a_pending_membership_and_notifies(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create();

        $member = $this->walletGroups()->invite($owner, $invitee, capUsd: 20.0, capNgn: null);

        $this->assertFalse($member->isActive());
        $this->assertSame(20.0, (float) $member->spend_cap_usd);
        $this->assertNull($member->spend_cap_ngn);
        $this->assertSame($owner->id, $member->walletGroup->owner_user_id);
        Notification::assertSentTo($invitee, WalletGroupInvitedNotification::class);
    }

    public function test_a_user_cannot_invite_themselves(): void
    {
        $owner = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->walletGroups()->invite($owner, $owner, null, null);
    }

    public function test_re_inviting_refreshes_the_cap_instead_of_duplicating(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();

        $this->walletGroups()->invite($owner, $invitee, 20.0, null);
        $this->walletGroups()->invite($owner, $invitee, 50.0, null);

        $this->assertDatabaseCount('wallet_group_members', 1);
        $member = WalletGroupMember::first();
        $this->assertSame(50.0, (float) $member->spend_cap_usd);
    }

    public function test_a_pending_member_cannot_spend_until_they_accept(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $this->wallet()->credit($owner, 100.0, 'USD', ['reference' => 'seed']);
        $member = $this->walletGroups()->invite($owner, $invitee, null, null);

        try {
            $this->walletGroups()->chargeFromGroup($member, 10.0, 'USD', fn () => true);
            $this->fail('Expected a 403 for an unaccepted invite.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_accepting_lets_the_member_spend_from_the_owners_wallet(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $this->wallet()->credit($owner, 100.0, 'USD', ['reference' => 'seed']);
        $member = $this->walletGroups()->invite($owner, $invitee, null, null);
        $this->walletGroups()->accept($member);

        $delivered = $this->walletGroups()->chargeFromGroup($member, 10.0, 'USD', fn () => 'delivered');

        $this->assertSame('delivered', $delivered);
        // The OWNER's wallet was debited, never the member's own (which has none).
        $this->assertSame('90.0000', (string) $owner->wallet->fresh()->usd_balance);
        $txn = WalletTransaction::where('user_id', $owner->id)->where('type', 'debit')->first();
        $this->assertSame($invitee->id, $txn->spent_by_user_id);
    }

    public function test_declining_removes_the_pending_row(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $member = $this->walletGroups()->invite($owner, $invitee, null, null);

        $this->walletGroups()->decline($member);

        $this->assertDatabaseMissing('wallet_group_members', ['id' => $member->id]);
    }

    // --- spend cap enforcement ----------------------------------------------

    public function test_a_capped_member_is_blocked_once_the_cap_is_reached(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $this->wallet()->credit($owner, 100.0, 'USD', ['reference' => 'seed']);
        $member = $this->walletGroups()->invite($owner, $invitee, capUsd: 15.0, capNgn: null);
        $this->walletGroups()->accept($member);

        $this->walletGroups()->chargeFromGroup($member, 10.0, 'USD', fn () => true);

        try {
            $this->walletGroups()->chargeFromGroup($member, 10.0, 'USD', fn () => true);
            $this->fail('Expected SpendCapExceededException.');
        } catch (SpendCapExceededException $e) {
            $this->assertSame($invitee->id, $e->memberId);
        }

        // The owner's wallet was only ever debited once — the second attempt
        // never reached WalletService at all.
        $this->assertSame('90.0000', (string) $owner->wallet->fresh()->usd_balance);
    }

    public function test_an_uncapped_member_can_spend_any_amount_the_owner_can_afford(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $this->wallet()->credit($owner, 100.0, 'USD', ['reference' => 'seed']);
        $member = $this->walletGroups()->invite($owner, $invitee, capUsd: null, capNgn: null);
        $this->walletGroups()->accept($member);

        $this->walletGroups()->chargeFromGroup($member, 40.0, 'USD', fn () => true);
        $this->walletGroups()->chargeFromGroup($member, 40.0, 'USD', fn () => true);

        $this->assertSame('20.0000', (string) $owner->wallet->fresh()->usd_balance);
    }

    public function test_a_refund_frees_up_the_members_spend_cap_again(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $this->wallet()->credit($owner, 100.0, 'USD', ['reference' => 'seed']);
        $member = $this->walletGroups()->invite($owner, $invitee, capUsd: 15.0, capNgn: null);
        $this->walletGroups()->accept($member);

        $this->walletGroups()->chargeFromGroup($member, 10.0, 'USD', fn () => true);
        $this->assertSame(10.0, $this->walletGroups()->totalSpent($member, 'USD'));

        $this->walletGroups()->refundToGroup($member, 10.0, 'USD', ['reference' => 'refund:1']);
        $this->assertSame(0.0, $this->walletGroups()->totalSpent($member, 'USD'));

        // Now able to spend the full cap again.
        $this->walletGroups()->chargeFromGroup($member, 15.0, 'USD', fn () => true);
        $this->assertSame(15.0, $this->walletGroups()->totalSpent($member, 'USD'));
    }

    public function test_the_cap_applies_independently_per_currency(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $this->wallet()->credit($owner, 100.0, 'USD', ['reference' => 'seed-usd']);
        $this->wallet()->credit($owner, 100000.0, 'NGN', ['reference' => 'seed-ngn']);
        $member = $this->walletGroups()->invite($owner, $invitee, capUsd: 10.0, capNgn: 5000.0);
        $this->walletGroups()->accept($member);

        $this->walletGroups()->chargeFromGroup($member, 10.0, 'USD', fn () => true);

        // The USD cap is exhausted, but the NGN cap is untouched.
        $this->walletGroups()->chargeFromGroup($member, 5000.0, 'NGN', fn () => true);
        $this->assertSame(5000.0, $this->walletGroups()->totalSpent($member, 'NGN'));
    }

    // --- owner management -----------------------------------------------------

    public function test_only_the_owner_can_update_a_cap_or_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $stranger = User::factory()->create();
        $member = $this->walletGroups()->invite($owner, $invitee, 10.0, null);

        try {
            $this->walletGroups()->updateCap($stranger, $member, 999.0, null);
            $this->fail('Expected a 403 for a non-owner.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->walletGroups()->updateCap($owner, $member, 30.0, null);
        $this->assertSame(30.0, (float) $member->fresh()->spend_cap_usd);

        $this->walletGroups()->remove($owner, $member);
        $this->assertDatabaseMissing('wallet_group_members', ['id' => $member->id]);
    }

    // --- additive-only guarantee (Prompt 11 §3 rule 4) -------------------------

    public function test_a_user_with_no_group_membership_sees_zero_change_to_their_own_wallet_flow(): void
    {
        $user = User::factory()->create();
        $this->wallet()->credit($user, 50.0, 'USD', ['reference' => 'seed']);

        $debit = $this->wallet()->debit($user, 10.0, 'USD', ['reference' => 'normal-purchase']);

        $this->assertNull($debit->spent_by_user_id);
        $this->assertSame('40.0000', (string) $user->wallet->fresh()->usd_balance);
    }

    /**
     * Tier 5 #11 Phase B2.3 — explicitly verify, rather than just trust the
     * architecture, that a shared-plan debit is shape/correctness-identical
     * to a normal one: both write balance_before/balance_after (money-safety
     * rule 5) and both round-trip the same amount/currency/type — the ONLY
     * difference a shared-plan debit adds is `spent_by_user_id` attribution.
     */
    public function test_a_shared_plan_debit_is_shape_identical_to_a_normal_debit(): void
    {
        $solo = User::factory()->create();
        $this->wallet()->credit($solo, 50.0, 'USD', ['reference' => 'seed-solo']);
        $normal = $this->wallet()->debit($solo, 10.0, 'USD', ['reference' => 'normal-purchase']);

        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $this->wallet()->credit($owner, 50.0, 'USD', ['reference' => 'seed-owner']);
        $member = $this->walletGroups()->invite($owner, $invitee, null, null);
        $this->walletGroups()->accept($member);
        $this->walletGroups()->chargeFromGroup($member, 10.0, 'USD', fn () => true);
        $shared = WalletTransaction::where('user_id', $owner->id)->where('type', 'debit')->firstOrFail();

        // Identical shape: same fields populated, same correctness guarantees.
        $this->assertSame($normal->type, $shared->type);
        $this->assertSame($normal->currency, $shared->currency);
        $this->assertSame((string) $normal->amount, (string) $shared->amount);
        $this->assertSame('40.0000', (string) $normal->balance_after);
        $this->assertSame('40.0000', (string) $shared->balance_after);
        $this->assertSame((string) $normal->balance_before, (string) $shared->balance_before);
        $this->assertNotNull($normal->balance_before);
        $this->assertNotNull($shared->balance_before);
        // The only real difference: attribution to the member who spent it.
        $this->assertNull($normal->spent_by_user_id);
        $this->assertSame($invitee->id, $shared->spent_by_user_id);
    }
}
