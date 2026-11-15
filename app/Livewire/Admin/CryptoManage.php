<?php

namespace App\Livewire\Admin;

use App\Models\Crypto\CryptoChain;
use App\Models\Crypto\CryptoDeposit;
use App\Models\Crypto\CryptoDepositAddress;
use App\Models\Crypto\CryptoRpcEndpoint;
use App\Models\Crypto\CryptoToken;
use App\Models\Setting;
use App\Services\Crypto\Chain\RpcClient;
use App\Services\Crypto\Chain\Units;
use App\Services\Crypto\Deposits\DepositProcessor;
use App\Services\Crypto\Support\DepositStatus;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin > Crypto top-up. Switches, per-network receiving addresses (an admin adds or retires the install's own addresses per network), RPC endpoints, the token allow-list with limits, and the deposit queue (approve held ones). Re-authorised on every request.
 */
#[Layout('components.layouts.admin')]
class CryptoManage extends Component
{
    public bool $enabled = false;

    public bool $paused = false;

    /** @var array<int,string> chain id => new address being typed */
    public array $newAddress = [];

    /** @var array<int,string> */
    public array $newLabel = [];

    /** @var array<int,string> */
    public array $primaryUrl = [];

    /** @var array<int,string> */
    public array $secondaryUrl = [];

    public string $reownId = '';

    public bool $mathRunning = false;

    /** @var array<int,array<string,string>> token id => limit fields being edited */
    public array $limits = [];

    public ?string $notice = null;

    public ?string $error = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->enabled = (bool) Setting::getValue('crypto.enabled', 0);
        $this->paused = (bool) Setting::getValue('crypto.credit_paused', 0);
        $this->reownId = (string) Setting::getValue('crypto.reown_project_id', '');
    }

    /** The Reown (WalletConnect) project id: public by design (it is sent to every browser), so it is stored plainly. Empty = injected wallets only. */
    public function saveReown(): void
    {
        $id = trim($this->reownId);
        if ($id !== '' && preg_match('/^[0-9a-f]{32}$/i', $id) !== 1) {
            $this->fail('A Reown project id is 32 letters and numbers. Copy it from cloud.reown.com.');

            return;
        }
        Setting::setValue('crypto.reown_project_id', $id, 'crypto');
        $this->done($id === '' ? 'WalletConnect is off: members can connect an injected wallet only.' : 'WalletConnect project saved. Members now get the full wallet picker.', 'reown_saved', ['set' => $id !== '']);
    }

    /** One-click install of gmp/bcmath where the server allows it (otherwise the page shows the exact steps for the host). */
    public function mathInstall(): void
    {
        $r = app(\App\Services\Crypto\Support\MathExtensionManager::class)->start();
        $this->mathRunning = $r['state'] === 'running';
        $r['state'] === 'failed' ? $this->fail($r['log']) : $this->done('Installing. This takes a minute or two.', 'math_install_started');
    }

    public function mathTick(): void
    {
        $p = app(\App\Services\Crypto\Support\MathExtensionManager::class)->poll();
        if (in_array($p['state'], ['done', 'failed', 'idle'], true)) {
            $this->mathRunning = false;
            $p['state'] === 'done' ? $this->done($p['verified'] ? 'Installed and checked. Wallet verification is ready.' : 'Installed. If this page still says missing, reload it in a minute.', 'math_installed') : null;
            $p['state'] === 'failed' ? $this->fail('The install did not finish. See the log below, or use the manual steps.') : null;
        }
    }

    private function done(string $msg, string $audit, array $payload = []): void
    {
        $this->error = null;
        $this->notice = $msg;
        Auditor::log('crypto.'.$audit, null, null, $payload);
    }

    private function fail(string $msg): void
    {
        $this->notice = null;
        $this->error = $msg;
    }

    public function saveSwitches(): void
    {
        Setting::setValue('crypto.enabled', $this->enabled ? 1 : 0, 'crypto');
        Setting::setValue('crypto.credit_paused', $this->paused ? 1 : 0, 'crypto');
        $this->done('Switches saved.', 'switches', ['enabled' => $this->enabled, 'paused' => $this->paused]);
    }

    public function toggleChain(int $chainId, string $field): void
    {
        if (! in_array($field, ['enabled', 'credit_enabled'], true) || ($c = CryptoChain::where('chain_id', $chainId)->first()) === null) {
            return;
        }
        if ($field === 'enabled' && ! $c->enabled && ! CryptoRpcEndpoint::where('chain_id', $chainId)->where('role', 'primary')->where('enabled', true)->exists()) {
            $this->fail('Add and test an RPC endpoint for '.$c->name.' before switching it on.');

            return;
        }
        if ($field === 'credit_enabled' && ! $c->credit_enabled && ! $c->enabled) {
            $this->fail('Switch watching on first.');

            return;
        }
        $c->forceFill([$field => ! $c->{$field}])->save();
        $this->done($c->name.' '.str_replace('_', ' ', $field).' is now '.($c->{$field} ? 'on' : 'off').'.', 'chain_'.$field, ['chain' => $chainId, 'to' => $c->{$field}]);
    }

    public function saveRpc(int $chainId, string $role): void
    {
        $url = trim((string) ($role === 'primary' ? ($this->primaryUrl[$chainId] ?? '') : ($this->secondaryUrl[$chainId] ?? '')));
        if (! preg_match('#^https://[^\s]+$#', $url)) {
            $this->fail('The endpoint must be a full https:// URL.');

            return;
        }
        try {
            $answers = (new RpcClient($url, 0, 1))->chainId();
        } catch (\Throwable) {
            $this->fail('That endpoint did not answer. Check the URL and key.');

            return;
        }
        if ($answers !== $chainId) {
            $this->fail("That endpoint is on chain {$answers}, not {$chainId}. It was not saved.");

            return;
        }
        CryptoRpcEndpoint::updateOrCreate(['chain_id' => $chainId, 'role' => $role], ['url_encrypted' => $url, 'enabled' => true, 'last_ok_at' => now(), 'last_error' => null]);
        unset($this->primaryUrl[$chainId], $this->secondaryUrl[$chainId]);
        $this->done(ucfirst($role).' endpoint saved and verified (it answers the right chain).', 'rpc_saved', ['chain' => $chainId, 'role' => $role]);
    }

    /** Add a receiving address for a network. Active at once: this is the admin's override of the official default; every change is audited. */
    public function addAddress(int $chainId): void
    {
        $a = trim((string) ($this->newAddress[$chainId] ?? ''));
        if (! Units::isAddress($a)) {
            $this->fail('That is not a valid EVM address (0x followed by 40 hex characters).');

            return;
        }
        if (CryptoChain::where('chain_id', $chainId)->doesntExist()) {
            return;
        }
        $row = CryptoDepositAddress::where('chain_id', $chainId)->where('address', strtolower($a))->first();
        if ($row !== null) {
            $row->forceFill(['status' => 'active'])->save();   // re-activating a retired address
        } else {
            CryptoDepositAddress::create(['chain_id' => $chainId, 'address' => $a, 'label' => trim((string) ($this->newLabel[$chainId] ?? '')) ?: 'Added by admin', 'status' => 'active', 'proposed_by' => Auth::id(), 'approved_by' => Auth::id(), 'test_confirmed_at' => now()]);
        }
        unset($this->newAddress[$chainId], $this->newLabel[$chainId]);
        $this->done('Address saved and active for scanning.', 'address_added', ['chain' => $chainId, 'address' => strtolower($a)]);
    }

    /** Retire keeps scanning for the grace window so a late payment to it is never lost. */
    public function retireAddress(int $id): void
    {
        $row = CryptoDepositAddress::find($id);
        if ($row === null) {
            return;
        }
        if (CryptoDepositAddress::where('chain_id', $row->chain_id)->where('status', 'active')->count() <= 1 && $row->status === 'active') {
            $this->fail('A network needs at least one active address. Add the new one first.');

            return;
        }
        $row->forceFill(['status' => 'retiring'])->save();
        $this->done('Address retiring: it is still scanned for '.(int) Setting::getValue('crypto.retire_scan_days', 45).' days.', 'address_retired', ['id' => $id]);
    }

    /** Run every check now, including one live read of each endpoint (short timeouts). */
    public function runSelfTest(): void
    {
        $r = (new \App\Services\Crypto\Health\CryptoSelfTest)->run();
        $w = \App\Services\Crypto\Health\CryptoSelfTest::worst($r);
        $this->done($w === 'ok' ? 'Everything checks out.' : ($w === 'warn' ? 'Checked: some items need attention.' : 'Checked: something is wrong. See the red items.'), 'selftest_run', ['worst' => $w]);
    }

    public function editLimits(int $id): void
    {
        if (($t = CryptoToken::find($id)) !== null) {
            $f = fn ($x) => rtrim(rtrim(number_format((float) $x, 2, '.', ''), '0'), '.');
            $this->limits[$id] = ['min' => $f($t->min_usd), 'max' => $f($t->max_single_usd), 'review' => $f($t->review_above_usd), 'daily' => $f($t->user_daily_cap_usd)];
        }
    }

    public function saveLimits(int $id): void
    {
        $t = CryptoToken::find($id);
        $in = $this->limits[$id] ?? null;
        if ($t === null || ! is_array($in)) {
            return;
        }
        $v = fn (string $k) => is_numeric($in[$k] ?? null) ? round((float) $in[$k], 2) : null;
        [$min, $max, $review, $daily] = [$v('min'), $v('max'), $v('review'), $v('daily')];
        if (in_array(null, [$min, $max, $review, $daily], true) || $min <= 0 || $min >= $max || $review > $max || $review <= $min || $daily < $max) {
            $this->fail('Check the limits: minimum above 0 and below the maximum; review line between them; daily cap at least the maximum single payment.');

            return;
        }
        $t->forceFill(['min_usd' => $min, 'max_single_usd' => $max, 'review_above_usd' => $review, 'user_daily_cap_usd' => $daily])->save();
        unset($this->limits[$id]);
        $this->done($t->symbol.' limits saved.', 'limits_saved', ['token' => $id, 'min' => $min, 'max' => $max, 'review' => $review, 'daily' => $daily]);
    }

    /** What still stands between this install and taking real payments, in plain words. @return list<array{ok: bool, title: string, detail: string, anchor: string}> */
    private function checklist(): array
    {
        $liveChains = CryptoChain::where('enabled', true)->where('credit_enabled', true)->pluck('chain_id');
        $rpcOk = $liveChains->isNotEmpty() && $liveChains->every(fn ($c) => CryptoRpcEndpoint::where('chain_id', $c)->where('role', 'primary')->where('enabled', true)->exists());
        $twoNodes = $liveChains->isNotEmpty() && $liveChains->every(fn ($c) => CryptoRpcEndpoint::where('chain_id', $c)->where('enabled', true)->count() >= 2);
        $addrOk = $liveChains->isNotEmpty() && $liveChains->every(fn ($c) => CryptoDepositAddress::where('chain_id', $c)->where('status', 'active')->exists());
        $tokOk = $liveChains->isNotEmpty() && $liveChains->every(fn ($c) => CryptoToken::where('chain_id', $c)->where('enabled', true)->exists());
        $cron = \App\Support\CronPulse::pulse()['state'] === 'live';

        return [
            ['ok' => (bool) Setting::getValue('crypto.enabled', 0) && ! (bool) Setting::getValue('crypto.credit_paused', 0), 'title' => 'Crypto top-up is switched on and not paused', 'detail' => 'The two switches at the top of this page.', 'anchor' => 'crypto-switches'],
            ['ok' => \App\Services\Crypto\Support\MathExtensionManager::loaded(), 'title' => 'Wallet verification support is installed (gmp or bcmath)', 'detail' => 'Without it members cannot link a wallet.', 'anchor' => 'crypto-math'],
            ['ok' => trim((string) Setting::getValue('crypto.reown_project_id', '')) !== '', 'title' => 'WalletConnect project id saved', 'detail' => 'Turns on the QR picker for 300+ mobile wallets. MetaMask, Trust and Coinbase in-app browsers work without it.', 'anchor' => 'crypto-reown'],
            ['ok' => $rpcOk, 'title' => 'Each live network has a working RPC endpoint', 'detail' => 'How we read the blockchain.', 'anchor' => 'crypto-chains'],
            ['ok' => $twoNodes, 'title' => 'Two independent endpoints per live network', 'detail' => 'Every deposit is double-checked on two providers before it is credited.', 'anchor' => 'crypto-chains'],
            ['ok' => $addrOk, 'title' => 'An active receiving address on each live network', 'detail' => 'Where the money lands: a wallet you control.', 'anchor' => 'crypto-chains'],
            ['ok' => $tokOk, 'title' => 'At least one accepted token on each live network', 'detail' => 'USDC and USDT only, using the issuers\' official contracts.', 'anchor' => 'crypto-chains'],
            ['ok' => $cron, 'title' => 'The scheduler (cron) is running', 'detail' => 'It runs the deposit scanner every minute. Fix it in Admin, System health.', 'anchor' => 'crypto-chains'],
            ['ok' => CryptoDeposit::where('status', DepositStatus::Credited->value)->exists(), 'title' => 'A real test payment has been credited', 'detail' => 'Send yourself $5 of USDC from your own wallet and watch it land. This proves the whole chain end to end.', 'anchor' => 'crypto-deposits'],
        ];
    }

    public function toggleToken(int $id): void
    {
        $t = CryptoToken::find($id);
        if ($t === null) {
            return;
        }
        if (! $t->enabled && blank($t->verified_source_note)) {
            $this->fail('Record the issuer page for this token before enabling it.');

            return;
        }
        $t->forceFill(['enabled' => ! $t->enabled, 'verified_by_admin_at' => $t->enabled ? $t->verified_by_admin_at : now()])->save();
        $this->done($t->symbol.' on chain '.$t->chain_id.' is now '.($t->enabled ? 'enabled' : 'disabled').'.', 'token_toggled', ['token' => $id, 'to' => $t->enabled]);
    }

    public function approve(int $depositId): void
    {
        $d = CryptoDeposit::find($depositId);
        if ($d === null || $d->state() !== DepositStatus::HeldReview) {
            return;
        }
        try {
            app(DepositProcessor::class)->approveHeld($d, (int) Auth::id());
            $this->done('Deposit approved and credited.', 'deposit_approved', ['deposit' => $depositId]);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
        }
    }

    public function reject(int $depositId): void
    {
        $d = CryptoDeposit::find($depositId);
        if ($d === null || $d->state() !== DepositStatus::HeldReview) {
            return;
        }
        $d->transition(DepositStatus::Rejected, 'admin:'.Auth::id(), 'rejected after review');
        $this->done('Deposit rejected. Nothing was credited.', 'deposit_rejected', ['deposit' => $depositId]);
    }

    public function render()
    {
        $chains = CryptoChain::orderBy('chain_id')->get();

        return view('livewire.admin.crypto-manage', [
            'chains' => $chains,
            'addresses' => CryptoDepositAddress::orderBy('chain_id')->orderBy('id')->get()->groupBy('chain_id'),
            'endpoints' => CryptoRpcEndpoint::get()->groupBy('chain_id'),
            'tokens' => CryptoToken::orderBy('chain_id')->orderBy('symbol')->get()->groupBy('chain_id'),
            'deposits' => CryptoDeposit::with('token')->latest('id')->limit(25)->get(),
            'health' => \App\Services\Crypto\Health\CryptoSelfTest::last(),
            'checklist' => $this->checklist(),
            'math' => app(\App\Services\Crypto\Support\MathExtensionManager::class)->status(),
            'mathLog' => $this->mathRunning || is_file(\App\Services\Crypto\Support\MathExtensionManager::logPath()) ? app(\App\Services\Crypto\Support\MathExtensionManager::class)->poll() : null,
            'held' => CryptoDeposit::where('status', DepositStatus::HeldReview->value)->count(),
        ])->title('Crypto top-up');
    }
}
