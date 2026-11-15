<?php

namespace App\Livewire;

use App\Models\Crypto\CryptoChain;
use App\Models\Crypto\CryptoDeposit;
use App\Models\Crypto\CryptoDepositAddress;
use App\Models\Crypto\CryptoLinkedWallet;
use App\Models\Crypto\CryptoToken;
use App\Services\Crypto\Chain\ChainRegistry;
use App\Services\Crypto\Link\SignatureVerifier;
use App\Services\Crypto\Link\WalletLinkService;
use App\Support\ComingSoon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Wallet > Crypto top-up. The member links the wallet they will pay from (a signature, free), then sends USDC or USDT to the official address shown.
 * We attribute the transfer to them by that wallet and credit once the chain says it is final. Nothing here can move funds or sign for the member.
 */
#[Layout('components.layouts.customer')]
class CryptoTopup extends Component
{
    public ?int $chainId = null;

    public string $address = '';

    /** @var list<string> the member's linked wallet addresses, refreshed on every render so the page can tell at once whether the connected wallet is already linked */
    public array $linkedList = [];

    public ?string $lastHash = null;

    public ?int $lastChain = null;

    public ?string $notice = null;

    public ?string $problem = null;

    public function mount(): void
    {
        ComingSoon::abortIfOff('crypto_topup');
        $chains = $this->chains();
        $this->chainId = ($chains->firstWhere('chain_id', 137) ?? $chains->first())?->chain_id;   // Polygon first: network fees are a fraction of a cent
    }

    /** Chains a member may pay on: watching AND crediting AND with an active address and at least one accepted token. */
    private function chains()
    {
        $reg = app(ChainRegistry::class);

        return $reg->enabledChains()->filter(fn (CryptoChain $c) => $reg->crediting((int) $c->chain_id)
            && CryptoDepositAddress::where('chain_id', $c->chain_id)->where('status', 'active')->exists()
            && CryptoToken::where('chain_id', $c->chain_id)->where('enabled', true)->exists())->values();
    }

    public function pickChain(int $id): void
    {
        if ($this->chains()->contains('chain_id', $id)) {
            $this->chainId = $id;
        }
    }

    /** Step 1 of linking: the server writes the exact words to sign. The browser wallet signs them (JavaScript) and sends the signature back to finishLink(). */
    public function startLink(string $address, ?int $chainId = null): void
    {
        $this->problem = $this->notice = null;
        try {
            $c = app(WalletLinkService::class)->challenge(Auth::user(), trim($address), $chainId ?: (int) $this->chainId);
            $this->dispatch('crypto-sign', id: $c['id'], message: $c['message'], address: strtolower(trim($address)));
        } catch (\Throwable $e) {
            $this->problem = $e instanceof \InvalidArgumentException || $e instanceof \DomainException ? $e->getMessage() : 'We could not start the check. Try again.';
        }
    }

    public function finishLink(int $id, string $signature): void
    {
        $this->problem = $this->notice = null;
        try {
            $w = app(WalletLinkService::class)->link(Auth::user(), $id, $signature, request()->ip(), request()->userAgent());
            $this->notice = 'Wallet linked: '.substr($w->address, 0, 8).'…'.substr($w->address, -4).'. Payments from it now credit your wallet.';
        } catch (\DomainException $e) {
            $this->problem = $e->getMessage();
        } catch (\Throwable $e) {
            report($e);
            $this->problem = 'We could not link that wallet. Try again.';
        }
    }

    /** The wallet has sent the payment: remember the hash (shape-checked, shown to the member) and run the scanner right after this response so it credits as soon as the chain is final. */
    public function submitted(string $hash, ?int $chainId = null): void
    {
        if (preg_match('/^0x[0-9a-fA-F]{64}$/', $hash) !== 1) {
            return;
        }
        $this->lastHash = strtolower($hash);
        $this->lastChain = $chainId;
        $this->notice = 'Payment sent. It credits once the network confirms it, usually within a few minutes. You can leave this page.';
        dispatch(function () {
            \Illuminate\Support\Facades\Artisan::call('crypto:scan');
        })->afterResponse();
    }

    public function walletRefused(string $why = ''): void
    {
        $this->problem = $why !== '' ? mb_substr($why, 0, 160) : 'The wallet did not sign. Nothing was linked.';
    }

    public function unlink(int $id): void
    {
        app(WalletLinkService::class)->unlink(Auth::user(), $id);
        $this->notice = 'Wallet unlinked.';
    }

    public function render()
    {
        $chains = $this->chains();
        $chain = $chains->firstWhere('chain_id', $this->chainId) ?? $chains->first();
        $deposit = $chain ? CryptoDepositAddress::where('chain_id', $chain->chain_id)->where('status', 'active')->orderBy('id')->first() : null;

        $this->linkedList = CryptoLinkedWallet::active()->where('user_id', Auth::id())->pluck('address')->map('strtolower')->all();
        $config = [
            'projectId' => (string) \App\Models\Setting::getValue('crypto.reown_project_id', ''),
            'appName' => config('app.name'),
            'chains' => $chains->pluck('chain_id')->map(fn ($c) => (int) $c)->all(),
            'byChain' => $chains->mapWithKeys(fn ($c) => [(int) $c->chain_id => [
                'name' => $c->name,
                'to' => CryptoDepositAddress::where('chain_id', $c->chain_id)->where('status', 'active')->orderBy('id')->value('address'),
                'tokens' => CryptoToken::where('chain_id', $c->chain_id)->where('enabled', true)->orderBy('symbol')->get()->map(fn ($t) => ['symbol' => $t->symbol, 'contract' => $t->contract, 'decimals' => (int) $t->decimals,
                    'min' => (float) $t->min_usd, 'max' => (float) $t->max_single_usd])->all(),
            ]])->all(),
        ];

        return view('livewire.crypto-topup', [
            'config' => $config,
            'chains' => $chains,
            'chain' => $chain,
            'depositAddress' => $deposit?->address,
            'tokens' => $chain ? CryptoToken::where('chain_id', $chain->chain_id)->where('enabled', true)->orderBy('symbol')->get() : collect(),
            'linked' => CryptoLinkedWallet::active()->where('user_id', Auth::id())->get(),
            'deposits' => CryptoDeposit::with('token')->where('attributed_user_id', Auth::id())->latest('id')->limit(10)->get(),
            'explorers' => CryptoChain::pluck('explorer_tx_url', 'chain_id')->all(),
            'canLink' => SignatureVerifier::available(),
        ])->title('Crypto top-up');
    }
}
