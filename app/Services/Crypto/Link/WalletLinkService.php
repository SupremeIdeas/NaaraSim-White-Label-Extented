<?php

namespace App\Services\Crypto\Link;

use App\Models\Crypto\CryptoDeposit;
use App\Models\Crypto\CryptoLinkedWallet;
use App\Models\Crypto\CryptoLinkNonce;
use App\Models\Setting;
use App\Models\User;
use App\Services\Crypto\Chain\Units;
use App\Services\Crypto\Support\DepositStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Proves a member owns a sending wallet: we issue a single-use, expiring challenge, the wallet signs it (EIP-191), we recover the signer and compare.
 * A linked wallet is what lets us attribute an incoming transfer to the right member, so one address can belong to ONE member at a time and the challenge is bound
 * to the member, the address and the chain. No private key is ever requested, stored or used here.
 */
class WalletLinkService
{
    /** @return array{id: int, message: string, expires_at: string} */
    public function challenge(User $user, string $address, int $chainId): array
    {
        if (! Units::isAddress($address)) {
            throw new \InvalidArgumentException('That is not a valid wallet address.');
        }
        if (! SignatureVerifier::available()) {
            throw new \DomainException('Wallet linking is not available on this server yet (the PHP gmp or bcmath extension is missing; an admin can install it in Admin, Crypto top-up).');
        }
        $ttl = max(1, (int) (Setting::getValue('crypto.link_nonce_ttl_minutes', 10)));
        $nonce = CryptoLinkNonce::create(['user_id' => $user->id, 'nonce' => bin2hex(random_bytes(16)), 'address' => strtolower($address), 'chain_id' => $chainId, 'message_hash' => str_repeat('0', 64), 'expires_at' => now()->addMinutes($ttl)]);
        $message = $this->message($nonce);
        $nonce->forceFill(['message_hash' => hash('sha256', $message)])->save();

        return ['id' => $nonce->id, 'message' => $message, 'expires_at' => $nonce->expires_at->toIso8601String()];
    }

    public function message(CryptoLinkNonce $n): string
    {
        return config('app.name', 'This site')." wants you to link this wallet to your account.\n\nAddress: {$n->address}\nChain ID: {$n->chain_id}\nNonce: {$n->nonce}\nIssued: ".$n->created_at->utc()->format('Y-m-d\TH:i:s\Z')
            ."\n\nSigning this message costs nothing and cannot move any funds.";
    }

    public function link(User $user, int $nonceId, string $signature, ?string $ip = null, ?string $ua = null): CryptoLinkedWallet
    {
        // Claim the challenge atomically FIRST and keep the claim even if the signature is wrong: one challenge, one attempt.
        $claimed = CryptoLinkNonce::whereKey($nonceId)->where('user_id', $user->id)->whereNull('consumed_at')->where('expires_at', '>', now())->update(['consumed_at' => now()]);
        if ($claimed !== 1) {
            throw new \DomainException('This signing request expired. Start again.');
        }
        $n = CryptoLinkNonce::findOrFail($nonceId);
        $signer = SignatureVerifier::recover($this->message($n), $signature);
        if ($signer === null || $signer !== strtolower($n->address)) {
            throw new \DomainException('The signature does not match that wallet.');
        }

        return DB::transaction(function () use ($user, $n, $signer, $ip, $ua) {
            if (CryptoLinkedWallet::active()->where('address', $signer)->where('user_id', '!=', $user->id)->lockForUpdate()->exists()) {
                throw new \DomainException('That wallet is already linked to another account.');
            }
            $w = CryptoLinkedWallet::active()->where('address', $signer)->where('user_id', $user->id)->first()
                ?? CryptoLinkedWallet::create(['user_id' => $user->id, 'address' => $signer, 'verified_at' => now(), 'last_nonce_id' => $n->id, 'ip_hash' => $ip ? hash('sha256', $ip) : null, 'ua_hash' => $ua ? hash('sha256', $ua) : null]);
            if ((bool) Setting::getValue('crypto.auto_attribute_on_link', 1)) {
                $this->attributeWaiting($signer, $user->id);
            }

            return $w;
        });
    }

    public function unlink(User $user, int $walletId): void
    {
        $w = CryptoLinkedWallet::active()->where('user_id', $user->id)->whereKey($walletId)->first();
        if ($w !== null) {
            $w->forceFill(['revoked_at' => now(), 'active_key' => 'r'.$w->id])->save();
        }
    }

    /** Deposits that arrived before the wallet was linked get their owner now; the worker credits them on its next pass. */
    private function attributeWaiting(string $address, int $userId): void
    {
        CryptoDeposit::where('from_address', $address)->where('status', DepositStatus::Unclaimed->value)->whereNull('attributed_user_id')->get()->each(function (CryptoDeposit $d) use ($userId) {
            $d->attributeTo($userId);
        });
    }
}
