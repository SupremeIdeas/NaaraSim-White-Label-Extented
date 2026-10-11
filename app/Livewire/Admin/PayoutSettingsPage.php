<?php

namespace App\Livewire\Admin;

use App\Jobs\AlertAdminJob;
use App\Models\Setting;
use App\Support\Auditor;
use App\Support\PayoutSettingsSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Payouts → All settings. One screen, rendered from PayoutSettingsSchema, that exposes every payout
 * knob with plain-English help, its unit, its default and a reset button. Super admin only. Every change is
 * validated, audited (old → new) and announced to the other super admins; changes to money-flow switches need
 * an explicit "I understand" tick.
 */
#[Layout('components.layouts.admin')]
class PayoutSettingsPage extends Component
{
    /** @var array<string, mixed> key => working value (dots in keys are encoded as "__") */
    public array $values = [];

    public string $search = '';

    public bool $confirmed = false;

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 403);
        $this->load();
    }

    private function load(): void
    {
        foreach (PayoutSettingsSchema::flat() as $key => $field) {
            $this->values[self::slot($key)] = PayoutSettingsSchema::current($field);
        }
    }

    public static function slot(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /** @return array<string, array{old: mixed, new: mixed, field: array}> keys whose working value differs from what is stored */
    private function dirty(): array
    {
        $out = [];
        foreach (PayoutSettingsSchema::flat() as $key => $field) {
            $new = $this->normalise($field, $this->values[self::slot($key)] ?? null);
            $old = PayoutSettingsSchema::current($field);
            if ($new !== $old) {
                $out[$key] = ['old' => $old, 'new' => $new, 'field' => $field];
            }
        }

        return $out;
    }

    private function normalise(array $field, mixed $v): mixed
    {
        return match ($field['type']) {
            'bool' => (bool) $v,
            'int' => (int) $v,
            'float' => round((float) $v, 4),
            'csv' => implode(',', array_filter(array_map(fn ($c) => strtoupper(trim($c)), explode(',', (string) $v)))),
            'json' => is_array($d = json_decode((string) $v, true)) ? json_encode(array_change_key_case($d, CASE_UPPER)) : '{}',
            default => (string) $v,
        };
    }

    public function resetField(string $slot): void
    {
        $key = str_replace('__', '.', $slot);
        $field = PayoutSettingsSchema::flat()[$key] ?? null;
        abort_unless($field && Auth::user()?->hasRole('super_admin'), 403);

        $old = PayoutSettingsSchema::current($field);
        Setting::where('key', $key)->first()?->delete(); // model delete, so the resolver cache is cleared
        $this->values[$slot] = PayoutSettingsSchema::current($field);
        Auditor::log('payout.setting_reset', 'Setting', 0, ['key' => $key, 'old' => $old, 'default' => $field['default']]);
        $this->dispatch('nx-toast', type: 'success', message: $field['label'].' reset to its default.');
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 403);
        $this->saved = null;
        $this->resetErrorBag();

        $dirty = $this->dirty();
        if ($dirty === []) {
            $this->saved = 'Nothing to save — everything already matches.';

            return;
        }

        $failed = false;
        foreach ($dirty as $key => $d) {
            $v = Validator::make(['v' => $d['new']], ['v' => PayoutSettingsSchema::rules($d['field'])], [], ['v' => $d['field']['label']]);
            if ($v->fails()) {
                $this->addError('values.'.self::slot($key), $v->errors()->first('v'));
                $failed = true;
            }
        }
        // Cross-field rule: the hold band can never sit below the approve band.
        $bands = [$this->bandValue('payouts.guardian.band_approve'), $this->bandValue('payouts.guardian.band_hold')];
        if ($bands[1] < $bands[0]) {
            $this->addError('values.'.self::slot('payouts.guardian.band_hold'), '"Hold above" cannot be lower than "approve below".');
            $failed = true;
        }
        if ($failed) {
            return;
        }

        $risky = array_filter($dirty, fn ($d) => $d['field']['danger'] ?? false);
        if ($risky !== [] && ! $this->confirmed) {
            $this->addError('confirmed', 'Please tick the box to confirm you understand these changes affect how money moves.');

            return;
        }

        $changes = [];
        foreach ($dirty as $key => $d) {
            Setting::setValue($key, $d['new'], 'payouts');
            $changes[$key] = ['from' => $d['old'], 'to' => $d['new']];
        }

        Auditor::log('payout.settings_changed', 'Setting', 0, ['by' => Auth::id(), 'changes' => $changes]);
        $summary = implode('; ', array_map(fn ($k, $c) => $k.': '.json_encode($c['from']).' → '.json_encode($c['to']), array_keys($changes), $changes));
        AlertAdminJob::dispatch(code: 'payout_settings_changed', message: 'Payout settings changed by '.(Auth::user()->email ?? 'an admin').': '.$summary, context: ['by' => Auth::id(), 'count' => count($changes)]);

        $this->confirmed = false;
        $this->load();
        $this->saved = count($changes).' setting'.(count($changes) === 1 ? '' : 's').' saved.';
        $this->dispatch('nx-toast', type: 'success', message: $this->saved);
    }

    private function bandValue(string $key): int
    {
        return (int) ($this->values[self::slot($key)] ?? 0);
    }

    public function render()
    {
        $q = strtolower(trim($this->search));
        $groups = [];
        foreach (PayoutSettingsSchema::groups() as $id => $g) {
            $fields = array_values(array_filter($g['fields'], fn ($f) => $q === '' || str_contains(strtolower($f['label'].' '.$f['help'].' '.$f['key']), $q)));
            if ($fields !== []) {
                $groups[$id] = ['title' => $g['title'], 'blurb' => $g['blurb'], 'fields' => $fields];
            }
        }
        $dirty = $this->dirty();

        return view('livewire.admin.payout-settings-page', [
            'groups' => $groups,
            'dirtyKeys' => array_keys($dirty),
            'needsConfirm' => array_filter($dirty, fn ($d) => $d['field']['danger'] ?? false) !== [],
        ]);
    }
}
