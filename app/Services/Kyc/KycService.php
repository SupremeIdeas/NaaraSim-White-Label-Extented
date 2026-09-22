<?php

namespace App\Services\Kyc;

use App\Models\KycVerification;
use App\Models\User;
use App\Notifications\KycResultNotification;
use App\Support\Auditor;
use App\Support\KycSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single owner of identity-verification state (ROADMAP §Layer 0.3). It picks
 * the admin-chosen provider (falling back to manual review), records each
 * attempt, applies synchronous or webhook decisions idempotently, and answers
 * the one question the withdraw/merchant gates ask: "is this user verified at
 * level N?". No raw ID numbers are persisted — only the provider's structured
 * result.
 */
class KycService
{
    /** @param list<KycProviderInterface> $providers */
    public function __construct(private array $providers)
    {
    }

    /** The active provider (admin choice), falling back to manual if unusable. */
    public function activeProvider(): KycProviderInterface
    {
        $name = KycSettings::provider();
        foreach ($this->providers as $provider) {
            if ($provider->name() === $name && $provider->available()) {
                return $provider;
            }
        }

        return $this->manual();
    }

    /**
     * Tier 5 #11 Phase C: L3 (business/KYB) is routed separately from L2.
     * Automated business verification only genuinely exists for Nigeria
     * today (Dojah's documented CAC/RC lookup) — confirmed during this
     * work that neither Dojah's nor Smile ID's existing integration covers
     * business-registry checks anywhere else. Every other country's L3
     * submission goes straight to manual review rather than being routed
     * through a personal-ID endpoint that would silently misfire on a
     * business registration number.
     *
     * This fork has no Stripe Identity / non-African global-KYC routing
     * (that's the UX/localization/platform-reach blueprint's Phase F,
     * master-only) — this only ever branches for L3.
     */
    public function resolveProvider(?string $country, int $level = KycVerification::L2): KycProviderInterface
    {
        $active = $this->activeProvider();

        if ($active->name() === 'manual') {
            return $active;
        }

        if ($level === KycVerification::L3) {
            if (filled($country) && strtoupper($country) === 'NG') {
                $dojah = $this->providerByName('dojah');
                if ($dojah !== null && $dojah->available()) {
                    return $dojah;
                }
            }

            return $this->manual();
        }

        return $active;
    }

    public function providerByName(?string $name): ?KycProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->name() === $name) {
                return $provider;
            }
        }

        return null;
    }

    private function manual(): KycProviderInterface
    {
        return $this->providerByName('manual') ?? throw new \RuntimeException('Manual KYC provider not registered.');
    }

    /** True if the user holds an approved verification at exactly this level. */
    public function hasLevel(User $user, int $level): bool
    {
        return KycVerification::query()
            ->where('user_id', $user->id)
            ->where('level', $level)
            ->where('status', KycVerification::APPROVED)
            ->exists();
    }

    /** The user's highest approved level (1 = the email/phone baseline everyone has). */
    public function currentLevel(User $user): int
    {
        return (int) KycVerification::query()
            ->where('user_id', $user->id)
            ->where('status', KycVerification::APPROVED)
            ->max('level') ?: 1;
    }

    /** The user's latest attempt at a level, if any (for status display). */
    public function latest(User $user, int $level): ?KycVerification
    {
        return KycVerification::query()
            ->where('user_id', $user->id)->where('level', $level)
            ->latest('id')->first();
    }

    /**
     * Submit a verification for a level. Returns the existing approved/pending
     * attempt rather than starting a duplicate, so a user can't spam the provider.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(User $user, int $level, array $data): KycVerification
    {
        // Already verified, or already awaiting a decision — reuse it.
        $existing = KycVerification::query()
            ->where('user_id', $user->id)->where('level', $level)
            ->whereIn('status', [KycVerification::APPROVED, KycVerification::PENDING])
            ->latest('id')->first();
        if ($existing !== null) {
            return $existing;
        }

        $provider = $this->resolveProvider($data['country'] ?? null, $level);

        return DB::transaction(function () use ($user, $level, $data, $provider) {
            $verification = KycVerification::create([
                'user_id' => $user->id,
                'level' => $level,
                'provider' => $provider->name(),
                'status' => KycVerification::PENDING,
                'reference' => 'kyc:'.Str::uuid(),
            ]);

            $result = $provider->submit($verification, $data);
            $verification->forceFill([
                'status' => $result->status,
                'checks' => $result->checks ?: null,
                'reason' => $result->reason,
                'reviewed_at' => in_array($result->status, [KycVerification::APPROVED, KycVerification::REJECTED, KycVerification::FAILED], true) ? now() : null,
            ])->save();

            Auditor::log('kyc.submitted', 'KycVerification', $verification->id, [
                'level' => $level, 'provider' => $provider->name(), 'status' => $verification->status,
            ]);

            $this->notifyOutcome($verification);

            return $verification;
        });
    }

    /** Apply a verified provider webhook to its verification (idempotent). */
    public function applyWebhook(KycEvent $event): ?KycVerification
    {
        $verification = KycVerification::query()->where('reference', $event->reference)->first();
        if ($verification === null || $verification->isFinal()) {
            return $verification;
        }

        $verification->forceFill([
            'status' => $event->status,
            'checks' => $event->checks ?: $verification->checks,
            'reason' => $event->reason,
            'reviewed_at' => now(),
        ])->save();
        Auditor::log('kyc.decided', 'KycVerification', $verification->id, ['status' => $event->status, 'via' => 'webhook']);

        $this->notifyOutcome($verification);

        return $verification;
    }

    /** Admin approves a pending (manual) verification. */
    public function approve(KycVerification $verification, User $reviewer): KycVerification
    {
        abort_unless($reviewer->hasAnyRole(['super_admin', 'admin']), 403);

        if ($verification->status === KycVerification::PENDING) {
            $verification->forceFill([
                'status' => KycVerification::APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();
            Auditor::log('kyc.approved', 'KycVerification', $verification->id, ['by' => $reviewer->id]);
            $this->notifyOutcome($verification);
        }

        return $verification;
    }

    /** Admin rejects a pending (manual) verification. */
    public function reject(KycVerification $verification, User $reviewer, string $reason = 'Not verified'): KycVerification
    {
        abort_unless($reviewer->hasAnyRole(['super_admin', 'admin']), 403);

        if ($verification->status === KycVerification::PENDING) {
            $verification->forceFill([
                'status' => KycVerification::REJECTED,
                'reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();
            Auditor::log('kyc.rejected', 'KycVerification', $verification->id, ['by' => $reviewer->id, 'reason' => $reason]);
            $this->notifyOutcome($verification);
        }

        return $verification;
    }

    /** Tell the user once a verification reaches a final decision (never for PENDING). */
    private function notifyOutcome(KycVerification $verification): void
    {
        if (! in_array($verification->status, [KycVerification::APPROVED, KycVerification::REJECTED, KycVerification::FAILED], true)) {
            return;
        }

        $verification->user?->notify(new KycResultNotification($verification->status, $verification->level, $verification->reason));
    }
}
