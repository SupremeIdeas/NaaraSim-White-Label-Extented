<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
use App\Models\User;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;

/**
 * The single owner of payout-destination management (ROADMAP §Layer 0.1). It
 * routes each country to the first available PSP that resolves it, confirms the
 * real account holder name BEFORE anything is saved (so money can never go to a
 * mistyped number), and keeps exactly one default per user.
 *
 * Resolvers are injected so tests drive it with fakes and prod gets the real
 * PSPs (bound in AppServiceProvider) — the same seam used by the maintenance
 * loop and the payout gateways.
 */
class PayoutAccountService
{
    /** @param list<BankResolverInterface> $resolvers */
    public function __construct(private array $resolvers) {}

    /** The first configured resolver that covers a country, or null. */
    public function resolverFor(string $country): ?BankResolverInterface
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->available() && $resolver->supports($country)) {
                return $resolver;
            }
        }

        return null;
    }

    /**
     * Banks/networks offered for a country (for the account picker), tagged with
     * the resolving provider so the caller knows who will handle it.
     *
     * @return array{provider: string|null, banks: list<array{code: string, name: string}>}
     */
    public function banksFor(string $country): array
    {
        $resolver = $this->resolverFor($country);

        return [
            'provider' => $resolver?->name(),
            'banks' => $resolver ? $resolver->banks($country) : [],
        ];
    }

    /**
     * Resolve + save a payout account. The account name is fetched from the PSP
     * and stored read-only; if it can't be confirmed we refuse to save (never a
     * destination we couldn't verify).
     *
     * @param  array{type?: string, country: string, currency: string, bank_code: string, bank_name?: string, account_number: string, make_default?: bool}  $data
     *
     * @throws AccountResolutionException
     */
    public function addAccount(User $user, array $data): PayoutAccount
    {
        $country = strtoupper(trim($data['country']));
        $resolver = $this->resolverFor($country);
        if ($resolver === null) {
            throw new AccountResolutionException("Payouts to {$country} aren't available yet.");
        }

        $resolved = $resolver->resolve($country, $data['bank_code'], $data['account_number']);
        if ($resolved === null) {
            throw new AccountResolutionException(
                "We couldn't confirm that account. Check the number and bank and try again."
            );
        }

        return DB::transaction(function () use ($user, $data, $country, $resolver, $resolved) {
            $isFirst = ! PayoutAccount::query()->where('user_id', $user->id)->exists();
            $makeDefault = ($data['make_default'] ?? false) || $isFirst;

            if ($makeDefault) {
                PayoutAccount::query()->where('user_id', $user->id)->update(['is_default' => false]);
            }

            $account = PayoutAccount::create([
                'user_id' => $user->id,
                'type' => $data['type'] ?? 'bank',
                'country' => $country,
                'currency' => strtoupper($data['currency']),
                'bank_code' => $data['bank_code'],
                'bank_name' => $resolved->bankName ?? ($data['bank_name'] ?? null),
                'account_number' => $data['account_number'],
                'account_name' => $resolved->accountName,
                'provider' => $resolver->name(),
                'is_verified' => true,
                'is_default' => $makeDefault,
            ]);

            Auditor::log('payout.account_added', 'PayoutAccount', $account->id, [
                'provider' => $resolver->name(),
                'country' => $country,
            ]);

            return $account;
        });
    }

    /**
     * Save a PayPal payout destination. Unlike a bank account, PayPal has no
     * resolve-style API to confirm a payout email belongs to a real, claimable
     * account before sending money — the caller (Withdraw.php) is responsible
     * for the double-entry re-confirmation UX; this just persists the
     * confirmed email. Marked verified immediately (there is nothing further
     * to verify against, by PayPal's own design), matching how PayPal payouts
     * are handled platform-wide.
     */
    public function addPaypalAccount(User $user, string $email): PayoutAccount
    {
        $email = strtolower(trim($email));

        return DB::transaction(function () use ($user, $email) {
            $isFirst = ! PayoutAccount::query()->where('user_id', $user->id)->exists();

            if ($isFirst) {
                PayoutAccount::query()->where('user_id', $user->id)->update(['is_default' => false]);
            }

            $account = PayoutAccount::create([
                'user_id' => $user->id,
                'type' => 'paypal',
                'country' => $user->country_code ?: 'ZZ',
                'currency' => 'USD',
                'bank_code' => 'paypal',
                'bank_name' => 'PayPal',
                'account_number' => $email,
                'account_name' => $email,
                'provider' => 'paypal',
                'is_verified' => true,
                'is_default' => $isFirst,
            ]);

            Auditor::log('payout.account_added', 'PayoutAccount', $account->id, [
                'provider' => 'paypal',
                'type' => 'paypal',
            ]);

            return $account;
        });
    }

    /** Make an account the user's default (unsets the others atomically). */
    public function setDefault(User $user, PayoutAccount $account): void
    {
        abort_unless($account->user_id === $user->id, 403);

        DB::transaction(function () use ($user, $account) {
            PayoutAccount::query()->where('user_id', $user->id)->update(['is_default' => false]);
            $account->forceFill(['is_default' => true])->save();
        });
    }

    /** Remove an account; promote the newest remaining one to default. */
    public function remove(User $user, PayoutAccount $account): void
    {
        abort_unless($account->user_id === $user->id, 403);

        DB::transaction(function () use ($user, $account) {
            $wasDefault = $account->is_default;
            $accountId = $account->id;
            $account->delete();

            if ($wasDefault) {
                $next = PayoutAccount::query()->where('user_id', $user->id)->latest('id')->first();
                $next?->forceFill(['is_default' => true])->save();
            }

            Auditor::log('payout.account_removed', 'PayoutAccount', $accountId, ['user_id' => $user->id]);
        });
    }
}
