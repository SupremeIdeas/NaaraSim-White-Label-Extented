<?php

namespace Tests\Unit\Crypto;

use App\Services\Crypto\Support\DepositStatus as S;
use PHPUnit\Framework\TestCase;

class DepositStatusTest extends TestCase
{
    public function test_a_credit_can_only_follow_a_verified_attributed_path(): void
    {
        $this->assertTrue(S::Detected->canTransitionTo(S::Confirming));
        $this->assertTrue(S::Verified->canTransitionTo(S::CreditPending));
        $this->assertTrue(S::CreditPending->canTransitionTo(S::Credited));
        $this->assertFalse(S::Detected->canTransitionTo(S::Credited), 'nothing skips verification');
        $this->assertFalse(S::Confirming->canTransitionTo(S::CreditPending));
        $this->assertFalse(S::Unclaimed->canTransitionTo(S::Credited));
    }

    public function test_terminal_states_never_move(): void
    {
        foreach ([S::Credited, S::Rejected, S::Reorged] as $s) {
            $this->assertTrue($s->isTerminal());
            foreach (S::cases() as $to) {
                $this->assertFalse($s->canTransitionTo($to), "{$s->value} must not move to {$to->value}");
            }
        }
    }

    public function test_attribution_freezes_from_credit_pending(): void
    {
        $this->assertTrue(S::CreditPending->freezesAttribution());
        $this->assertTrue(S::Credited->freezesAttribution());
        $this->assertFalse(S::Unclaimed->freezesAttribution());
    }

    public function test_every_status_in_the_enum_is_a_value_the_database_column_can_hold(): void
    {
        foreach (S::cases() as $s) {
            $this->assertLessThanOrEqual(20, strlen($s->value));
        }
    }
}
