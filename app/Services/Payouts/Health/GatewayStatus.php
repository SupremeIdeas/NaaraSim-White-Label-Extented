<?php

namespace App\Services\Payouts\Health;

/** One payout gateway's honest state. `reason` is for admins (full detail); `userReason` is the calm, non-sensitive line a member may see. */
final class GatewayStatus
{
    public const R_OK = 'ok';

    public const R_NO_KEY = 'no_key';

    public const R_ENV_MISMATCH = 'key_present_env_mismatch';

    public const R_UNAVAILABLE = 'gateway_unavailable';

    public const R_BREAKER = 'breaker_tripped';

    public const R_NOT_INSTALLED = 'extension_not_installed';

    public const R_EXT_PROBLEM = 'extension_problem';

    public const R_DISABLED = 'disabled_by_admin';

    /** States shown in Payout Health (blueprint 2.2). */
    public const S_NOT_CONFIGURED = 'not_configured';

    public const S_CONFIGURED = 'configured';

    public const S_BLOCKED = 'blocked';

    public const S_FAILING = 'failing';

    public const S_OFF = 'disabled_by_admin';

    public function __construct(
        public readonly string $provider,
        public readonly string $label,
        public readonly string $state,
        public readonly string $reason,
        public readonly string $adminMessage,
        public readonly ?string $fix,
        public readonly bool $installed,
        public readonly bool $core,
    ) {
    }

    /** Can this gateway take new payouts right now? */
    public function usable(): bool
    {
        return $this->reason === self::R_OK;
    }

    /**
     * The calm line a member may see. Only a tripped failure breaker is "temporarily unavailable"; a rail that simply is not set up yet is
     * "not open yet". Never a key name, mode, or internal detail.
     */
    public function userReason(): string
    {
        return match ($this->reason) {
            self::R_OK => 'available',
            self::R_BREAKER => 'temporarily_unavailable',
            default => 'not_open_yet',
        };
    }
}
