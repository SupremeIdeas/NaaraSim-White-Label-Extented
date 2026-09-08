<?php

namespace App\Livewire;

use App\Exceptions\InsufficientBalanceException;
use App\Models\ApiClient;
use App\Services\Api\ApiClientService;
use App\Services\Api\ApiWalletService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Developer portal (ROADMAP §Layer 2). Where a developer manages their API
 * clients: create a key (plaintext shown ONCE), rotate/revoke it, choose scopes,
 * and top up the client's prepaid API balance from their own USD wallet. All
 * actions are scoped to keys the signed-in user owns; the money move for a
 * top-up runs through WalletService + ApiWalletService (atomic, refunded if the
 * credit can't post) so a top-up can never lose the user's money.
 */
#[Layout('components.layouts.customer')]
class DeveloperPortal extends Component
{
    public string $name = '';

    /** @var array<int, string> */
    public array $scopes = ['catalogue', 'quote', 'order', 'status'];

    /** Plaintext key shown once immediately after create/rotate. */
    public ?string $plainToken = null;

    public array $topUp = []; // clientId => amount (string)

    public ?string $error = null;

    public function create(ApiClientService $clients): void
    {
        $this->error = null;
        $this->plainToken = null;
        $this->validate([
            'name' => 'required|string|max:60',
            'scopes' => 'array',
            'scopes.*' => 'in:'.implode(',', ApiClient::SCOPES),
        ]);

        if ($this->rateLimited()) {
            return;
        }

        $result = $clients->create(auth()->user(), $this->name, $this->scopes);
        $this->plainToken = $result['token']; // one-time reveal
        $this->reset('name');
        $this->scopes = ['catalogue', 'quote', 'order', 'status'];

        $this->dispatch('nx-toast', type: 'success', title: 'API key created',
            message: 'Copy your key now — it won’t be shown again.');
    }

    public function rotate(ApiClientService $clients, int $clientId): void
    {
        $this->error = null;
        $client = $this->ownedClient($clientId);
        if (! $client || $this->rateLimited()) {
            return;
        }

        $this->plainToken = $clients->rotate($client);
        $this->dispatch('nx-toast', type: 'success', title: 'Key rotated',
            message: 'Your old key stopped working. Copy the new one now.');
    }

    public function revoke(ApiClientService $clients, int $clientId): void
    {
        $this->error = null;
        $this->plainToken = null;
        if ($client = $this->ownedClient($clientId)) {
            $clients->revoke($client);
            $this->dispatch('nx-toast', type: 'success', title: 'Key revoked',
                message: 'That client can no longer make API calls.');
        }
    }

    /** Move USD from the user's wallet into the client's prepaid API balance. */
    public function fund(WalletService $wallet, ApiWalletService $apiWallet, int $clientId): void
    {
        $this->error = null;
        $client = $this->ownedClient($clientId);
        if (! $client) {
            return;
        }

        $amount = round((float) ($this->topUp[$clientId] ?? 0), 2);
        if ($amount < 1) {
            $this->error = 'Enter an amount of at least $1.00 to top up.';

            return;
        }
        if ($this->rateLimited()) {
            return;
        }

        $ref = "api-topup:{$client->id}:".now()->timestamp;
        try {
            $wallet->debit(auth()->user(), $amount, 'USD', ['reference' => $ref, 'description' => "API balance top-up ({$client->name})"]);
        } catch (InsufficientBalanceException) {
            $this->error = 'Your wallet balance is too low. Top up your wallet first.';

            return;
        }

        // Credit the API balance; if that somehow fails, return the money.
        try {
            $apiWallet->credit($client, $amount, ['reference' => $ref, 'description' => 'Top-up from wallet']);
        } catch (Throwable $e) {
            $wallet->refund(auth()->user(), $amount, 'USD', ['reference' => "refund:{$ref}", 'description' => 'API top-up reversed']);
            $this->error = 'Top-up could not be completed — your wallet was not charged.';

            return;
        }

        unset($this->topUp[$clientId]);
        $this->dispatch('nx-toast', variant: 'hero', type: 'success', title: 'API balance topped up',
            message: '$'.number_format($amount, 2).' added to '.$client->name.'.');
    }

    /** Dismiss the one-time token reveal. */
    public function hideToken(): void
    {
        $this->plainToken = null;
    }

    private function ownedClient(int $clientId): ?ApiClient
    {
        return ApiClient::where('owner_user_id', auth()->id())->find($clientId);
    }

    private function rateLimited(): bool
    {
        $key = 'dev-portal:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->error = 'Too many requests — please wait a moment.';

            return true;
        }
        RateLimiter::hit($key, 60);

        return false;
    }

    public function render()
    {
        return view('livewire.developer-portal', [
            'clients' => ApiClient::where('owner_user_id', auth()->id())->latest()->get(),
            'walletUsd' => (float) (auth()->user()?->wallet?->usd_balance ?? 0),
            'allScopes' => ApiClient::SCOPES,
        ]);
    }
}
