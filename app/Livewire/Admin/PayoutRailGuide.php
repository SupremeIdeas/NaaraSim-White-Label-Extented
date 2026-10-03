<?php

namespace App\Livewire\Admin;

use App\Models\PayoutCountryRail;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\Rail\PayoutRailRegistry;
use App\Services\Payouts\Rail\RailAdvisor;
use App\Services\Payouts\Rail\RailGuideReport;
use App\Services\Payouts\Rail\RailRegistrySeeder;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Admin controls for the Rail Guide (Addendum B §6): the country x rail matrix editor
 * (force on/off, ETA overrides), guide settings, "run audit"/"re-seed" (never overwriting
 * an override), a preview simulator and the funnel report. Every edit is audited and
 * flushes the registry cache. Admin roles only.
 */
class PayoutRailGuide extends Component
{
    public string $country = 'NG';

    public $etaMin = '';

    public $etaMax = '';

    public bool $allowGlobal = false;

    public $globalDays = 14;

    public $guideVersion = 1;

    /** @var array<string, string> */
    public array $etaText = [];

    // preview-as simulator (writes nothing)
    public string $previewCountry = 'NG';

    public string $previewHistoryRail = '';

    public ?array $preview = null;

    public ?string $auditResult = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->allowGlobal = (bool) Setting::getValue('payouts.global_rail.allow_when_local_available', false);
        $this->globalDays = (int) Setting::getValue('payouts.eta.global_days', 14);
        $this->guideVersion = (int) Setting::getValue('payouts.guide.version', 1);
        foreach (['paystack', 'flutterwave', 'stripe_connect', 'global'] as $r) {
            $this->etaText[$r] = (string) Setting::getValue("payouts.eta.{$r}", '');
        }
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function saveSettings(PayoutRailRegistry $registry): void
    {
        $this->authorizeAdmin();
        $this->validate(['globalDays' => 'required|integer|min:1|max:60', 'guideVersion' => 'required|integer|min:1|max:100000', 'etaText.*' => 'nullable|string|max:120']);

        Setting::setValue('payouts.global_rail.allow_when_local_available', $this->allowGlobal, 'payouts');
        Setting::setValue('payouts.eta.global_days', (int) $this->globalDays, 'payouts');
        Setting::setValue('payouts.guide.version', (int) $this->guideVersion, 'payouts');
        foreach ($this->etaText as $rail => $text) {
            Setting::setValue("payouts.eta.{$rail}", trim($text), 'payouts');
        }
        Auditor::log('payouts.guide_settings_updated', null, null, ['allow_global' => $this->allowGlobal, 'global_days' => $this->globalDays, 'version' => $this->guideVersion]);
        $registry->flush();
        $this->dispatch('nx-toast', type: 'success', message: 'Guide settings saved.');
    }

    /** force_on / force_off / none for one country+rail. Survives re-seeding. */
    public function setOverride(string $rail, string $override, PayoutRailRegistry $registry): void
    {
        $this->authorizeAdmin();
        abort_unless(in_array($override, ['none', 'force_on', 'force_off'], true) && in_array($rail, PayoutRailRegistry::RAILS, true), 422);

        $row = PayoutCountryRail::firstOrNew(['country' => strtoupper($this->country), 'rail' => $rail]);
        $row->fill(['admin_override' => $override, 'provider_supports' => $row->provider_supports ?? false, 'verified_by' => 'admin:'.Auth::id(), 'verified_at' => now()])->save();

        Auditor::log('payouts.guide_override', 'PayoutCountryRail', $row->id, ['country' => $row->country, 'rail' => $rail, 'override' => $override]);
        $registry->flush();
        $this->dispatch('nx-toast', type: 'success', message: 'Override saved.');
    }

    public function saveEta(string $rail, PayoutRailRegistry $registry): void
    {
        $this->authorizeAdmin();
        $this->validate(['etaMin' => 'nullable|integer|min:0|max:2000', 'etaMax' => 'nullable|integer|min:0|max:2000']);
        $row = PayoutCountryRail::firstOrNew(['country' => strtoupper($this->country), 'rail' => $rail]);
        $row->fill([
            'eta_min_hours' => $this->etaMin === '' ? null : (int) $this->etaMin,
            'eta_max_hours' => $this->etaMax === '' ? null : (int) $this->etaMax,
            'provider_supports' => $row->provider_supports ?? false,
        ])->save();
        Auditor::log('payouts.guide_eta', 'PayoutCountryRail', $row->id, ['country' => $row->country, 'rail' => $rail, 'min' => $row->eta_min_hours, 'max' => $row->eta_max_hours]);
        $registry->flush();
        $this->dispatch('nx-toast', type: 'success', message: 'ETA saved.');
    }

    public function runAudit(RailRegistrySeeder $seeder): void
    {
        $this->authorizeAdmin();
        $r = $seeder->audit();
        Auditor::log('payouts.guide_audit_run', null, null, ['drift' => count($r['drift']), 'stale' => $r['stale']]);
        $this->auditResult = count($r['drift']).' disagreement(s) with providers, '.$r['stale'].' row(s) need re-verification.';
    }

    /** Re-seed from providers; an admin override is never overwritten. */
    public function reseed(RailRegistrySeeder $seeder): void
    {
        $this->authorizeAdmin();
        $r = $seeder->run();
        Auditor::log('payouts.guide_reseed', null, null, ['seeded' => $r['seeded'], 'drift' => count($r['drift'])]);
        $this->auditResult = "Re-seeded: {$r['seeded']} new row(s), ".count($r['drift']).' disagreement(s).'.($r['skipped'] ? ' Skipped: '.implode(', ', $r['skipped']) : '');
    }

    /** "View the guide as country X / as a user with history Y" — simulation only, nothing is written. */
    public function runPreview(RailAdvisor $advisor): void
    {
        $this->authorizeAdmin();
        $ghost = new User(['country_code' => strtoupper($this->previewCountry)]);
        $ghost->id = 0;
        $history = $this->previewHistoryRail;

        $advice = $advisor->advise($ghost, $this->previewCountry);
        // Reorder as if the simulated user had paid through $history (pure display; no DB access).
        if ($history !== '') {
            usort($advice['options'], fn ($a, $b) => [$b['rail'] === $history ? 1 : 0] <=> [$a['rail'] === $history ? 1 : 0]);
        }
        $this->preview = ['verdict' => $advice['verdict'], 'options' => array_map(fn ($o) => $o['rail'].' = '.$o['state'], $advice['options'])];
    }

    public function render(PayoutRailRegistry $registry, RailGuideReport $report)
    {
        $this->authorizeAdmin();
        $rows = [];
        foreach (PayoutRailRegistry::RAILS as $rail) {
            $row = $registry->note($this->country, $rail);
            $rows[] = [
                'rail' => $rail, 'state' => $registry->state($this->country, $rail), 'override' => $row?->admin_override ?? 'none',
                'verified_at' => $row?->verified_at, 'eta_min' => $row?->eta_min_hours, 'eta_max' => $row?->eta_max_hours,
                'stale' => $row?->provider_supports && ($row->verified_at === null || $row->verified_at->lt(now()->subDays(45))),
            ];
        }

        return view('livewire.admin.payout-rail-guide', [
            'rows' => $rows, 'report' => $report->build(), 'countries' => PayoutRailRegistry::countryCodes(),
        ]);
    }
}
