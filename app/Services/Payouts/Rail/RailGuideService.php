<?php

namespace App\Services\Payouts\Rail;

use App\Models\PayoutCorridor;
use App\Models\PayoutGuideEvent;
use App\Models\PayoutRailAcknowledgement;
use App\Models\PayoutRailEnrollment;
use App\Models\User;
use App\Services\Payouts\PayoutException;
use Illuminate\Http\Request;

/**
 * The SERVER-SIDE half of the Rail Guide (Addendum B §5.3 / G4). Everything the UI offers is
 * re-checked here, so a direct Livewire call cannot bypass the guide: a rail can only be
 * chosen if the advisor offers it for that country, and the global rail additionally needs
 * the recorded acknowledgement (current guide version) before the user is enrolled.
 */
class RailGuideService
{
    /** Option states a user may actually pick. */
    private const SELECTABLE = ['recommended', 'available', 'global_fallback'];

    public function __construct(private RailAdvisor $advisor, private RailEnrollmentService $enrollments) {}

    public function track(?User $user, string $event, ?string $country = null, ?string $rail = null, array $meta = []): void
    {
        PayoutGuideEvent::create(['user_id' => $user?->id, 'event' => $event, 'country' => $country ? strtoupper($country) : null, 'rail' => $rail, 'meta' => $meta ?: null]);
    }

    /**
     * @return string 'form' (continue to the rail's existing setup) | 'acknowledge' (global: tick the boxes first)
     *
     * @throws PayoutException
     */
    public function choose(User $user, string $rail, string $country): string
    {
        $state = $this->stateOf($user, $rail, $country);
        if ($state === 'disabled') {
            throw new PayoutException((string) __('payout_guide.global_blocked_local'));
        }
        if (! in_array($state, self::SELECTABLE, true)) {
            throw new PayoutException((string) __('payout_guide.state_not_supported', ['country' => PayoutRailRegistry::countryName($country)]));
        }

        $this->track($user, 'rail_selected', $country, $rail, ['state' => $state]);

        return $rail === 'global' ? 'acknowledge' : 'form';
    }

    /**
     * Record the acknowledgement and enroll the user for Funding Radar tracking.
     *
     * @param  array{0?: bool, 1?: bool, 2?: bool}  $checks all three must be true
     *
     * @throws PayoutException
     */
    public function acknowledgeGlobal(User $user, string $country, array $checks, ?Request $request = null): PayoutRailEnrollment
    {
        if (count(array_filter($checks)) < 3) {
            throw new PayoutException((string) __('payout_guide.ack_required'));
        }
        $this->choose($user, 'global', $country); // same gate as the UI: offered AND selectable

        $provider = $this->globalProviderFor($country)
            ?? throw new PayoutException((string) __('payout_guide.state_not_supported', ['country' => PayoutRailRegistry::countryName($country)]));

        $request ??= request();
        PayoutRailAcknowledgement::create([
            'user_id' => $user->id, 'rail' => 'global', 'country' => strtoupper($country), 'guide_version' => RailCopy::guideVersion(),
            'ip_hash' => $request?->ip() ? hash('sha256', $request->ip().config('app.key')) : null,
            'user_agent_hash' => $request?->userAgent() ? hash('sha256', $request->userAgent().config('app.key')) : null,
        ]);
        $this->track($user, 'global_ack_confirmed', $country, 'global');

        return $this->enrollments->select($user, $provider, $country, null, enforce: true);
    }

    /**
     * Rule for adding/enrolling on a global provider: the advisor must offer global for that
     * country AND the user must have acknowledged the current guide version.
     *
     * @throws PayoutException
     */
    public function assertMayEnroll(User $user, string $provider, string $country): void
    {
        if (! RailEnrollmentService::isGlobal($provider)) {
            return;
        }
        $state = $this->stateOf($user, 'global', $country);
        if ($state === 'disabled') {
            throw new PayoutException((string) __('payout_guide.global_blocked_local'));
        }
        if (! in_array($state, self::SELECTABLE, true)) {
            throw new PayoutException((string) __('payout_guide.state_not_supported', ['country' => PayoutRailRegistry::countryName($country)]));
        }
        if (! $this->hasAcknowledged($user, 'global', $country)) {
            throw new PayoutException((string) __('payout_guide.ack_required'));
        }
    }

    public function hasAcknowledged(User $user, string $rail, string $country): bool
    {
        return PayoutRailAcknowledgement::where('user_id', $user->id)->where('rail', $rail)->where('country', strtoupper($country))
            ->where('guide_version', RailCopy::guideVersion())->exists();
    }

    /** "Notify me when a rail opens for my country" — once per user and country. */
    public function requestNotify(User $user, string $country): void
    {
        $exists = PayoutGuideEvent::where('user_id', $user->id)->where('event', 'blocked_notify_requested')->where('country', strtoupper($country))->exists();
        $exists || $this->track($user, 'blocked_notify_requested', $country);
    }

    /** The first enabled global provider (by priority) that pays this country. */
    public function globalProviderFor(string $country): ?string
    {
        return PayoutCorridor::query()->enabled()->where('country', strtoupper($country))
            ->whereIn('provider', RailEnrollmentService::globalProviders())->orderBy('priority')->value('provider');
    }

    private function stateOf(User $user, string $rail, string $country): string
    {
        $advice = $this->advisor->advise($user, $country);

        return collect($advice['options'])->firstWhere('rail', $rail)['state'] ?? 'not_supported';
    }
}
