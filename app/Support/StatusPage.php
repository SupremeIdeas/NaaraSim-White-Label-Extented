<?php

namespace App\Support;

use App\Models\Incident;

/**
 * Public status page model (Section Builder §1). Component health is DERIVED from
 * the existing ProviderStatus config (no second status source), then aggregated
 * into BRANDED service groups. Crucially it never names a supplier — the
 * money-safety supplier-identity scrub (rule 1.2) forbids leaking provider
 * brands to users, so each public component fronts one-or-more hidden providers.
 * An open incident on a component overrides its derived state.
 */
class StatusPage
{
    /** Branded public component => the (hidden) providers whose health it fronts. */
    private const COMPONENTS = [
        'data' => ['label' => 'eSIM Data network', 'providers' => ['esimgo', 'airalo', 'quibity', 'zendit']],
        'connect' => ['label' => 'Naara Connect (Full eSIM)', 'providers' => ['zendit', 'oneglobal', 'montymobile', 'gigs']],
        'numbers' => ['label' => 'Numbers — Verify & Rent', 'providers' => ['fivesim', 'getatext', 'herosms', 'virtsms', 'smspool', 'onlinesim']],
        'line' => ['label' => 'Naara Line (voice + SMS)', 'providers' => ['twilio', 'telnyx', 'vonage', 'sinch', 'plivo', 'sonetel']],
        'payments' => ['label' => 'Payments & Wallet', 'providers' => ['paystack', 'flutterwave', 'stripe']],
        'api' => ['label' => 'Developer API', 'providers' => []],
        'core' => ['label' => 'Core platform', 'providers' => []],
    ];

    /** States, worst-first, so the overall banner can pick the worst. */
    public const STATE_ORDER = ['major_outage', 'partial_outage', 'degraded', 'maintenance', 'operational', 'not_live'];

    /**
     * @return array<int, array{key:string, label:string, state:string}>
     */
    public static function components(): array
    {
        $openByComponent = self::openIncidentsByComponent();

        return collect(self::COMPONENTS)->map(function ($def, $key) use ($openByComponent) {
            return [
                'key' => $key,
                'label' => $def['label'],
                'state' => self::deriveState($key, $def, $openByComponent[$key] ?? null),
            ];
        })->values()->all();
    }

    /** The single worst state across all components, for the top banner. */
    public static function overall(): string
    {
        $states = array_column(self::components(), 'state');
        foreach (self::STATE_ORDER as $s) {
            if (in_array($s, $states, true) && $s !== 'not_live') {
                return $s;
            }
        }

        return 'operational';
    }

    public static function overallLabel(): string
    {
        return match (self::overall()) {
            'major_outage' => 'Major outage',
            'partial_outage' => 'Partial outage',
            'degraded' => 'Degraded performance',
            'maintenance' => 'Under maintenance',
            default => 'All systems operational',
        };
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            'major_outage' => 'Major outage',
            'partial_outage' => 'Partial outage',
            'degraded' => 'Degraded',
            'maintenance' => 'Maintenance',
            'not_live' => 'Not yet live',
            default => 'Operational',
        };
    }

    private static function deriveState(string $key, array $def, ?Incident $incident): string
    {
        if ($incident) {
            return match ($incident->impact) {
                'critical' => 'major_outage',
                'major' => 'partial_outage',
                'maintenance' => 'maintenance',
                default => 'degraded',
            };
        }

        if ($key === 'core') {
            return 'operational';
        }
        if ($key === 'api') {
            return (bool) \App\Models\Setting::getValue('api.enabled', false) ? 'operational' : 'not_live';
        }

        // Operational when at least one fronted provider is live; else not-yet-live.
        foreach ($def['providers'] as $p) {
            if (ProviderStatus::isActive($p)) {
                return 'operational';
            }
        }

        return 'not_live';
    }

    /** @return array<string, Incident> component key => most-recent open incident */
    private static function openIncidentsByComponent(): array
    {
        return Incident::where('status', '!=', 'resolved')
            ->whereNotNull('component')
            ->latest('started_at')
            ->get()
            ->groupBy('component')
            ->map(fn ($group) => $group->first())
            ->all();
    }

    /** Active + recently-resolved incidents for the public timeline (90 days). */
    public static function timeline()
    {
        return Incident::with('updates')
            ->where(fn ($q) => $q->where('status', '!=', 'resolved')
                ->orWhere('resolved_at', '>=', now()->subDays(90)))
            ->orderByRaw("CASE WHEN status = 'resolved' THEN 1 ELSE 0 END")
            ->latest('started_at')
            ->get();
    }
}
