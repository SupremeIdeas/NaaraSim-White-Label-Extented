<?php

namespace App\PayoutRails\Grey;

use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use App\Models\User;
use App\Services\Payouts\AccountResolutionException;
use App\Services\Payouts\Hardening\PayoutFreeze;
use App\Services\Payouts\Hardening\StepUpAuth;
use App\Support\Auditor;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Lets a user save an international bank / mobile-money account to be paid through Grey.
 *
 * Nothing is guessed: the country must have an ENABLED Grey corridor, and the form's fields are exactly what Grey's
 * own /v1/payout-methods says that corridor requires (minus the ones we fill ourselves). Grey exposes no
 * account-name resolve call, so the account holder name is the user's own verified name (never typed), and Grey
 * payouts stay on manual review in the Guardian until the owner switches that off.
 */
class GreyAccountService
{
    /** Fields we fill in ourselves; the user is never asked for these. */
    private const AUTO = ['first_name', 'last_name', 'bank_country_code', 'payment_purpose', 'source_of_funds', 'destination', 'scheme'];

    /** Fields a user can supply. A required field outside this list means we cannot take that corridor yet. */
    public const USER_FIELDS = ['account_number', 'bank_code', 'bank_name', 'routing_number', 'sort_code', 'swift_code', 'iban', 'account_type', 'branch_code', 'network_type', 'document_number', 'phone_number', 'recipient_type', 'wallet_id'];

    public function __construct(private readonly GreyClient $client, private readonly GreyBeneficiaryMapper $mapper)
    {
    }

    /** Countries with an enabled Grey corridor, as [CC => [currency, method]]. */
    public function countries(): array
    {
        $out = [];
        foreach (PayoutCorridor::query()->enabled()->where('provider', 'grey')->orderBy('priority')->get() as $c) {
            $out[$c->country] ??= ['currency' => $c->currency, 'method' => $c->method];
        }

        return $out;
    }

    /**
     * What the member is asked for. Grey's `required_fields` is a UNION across every country on a rail, so it cannot say
     * what one country needs. We therefore require only what every account needs (`fields`) and offer the rest of
     * Grey's list as optional (`optional`); Grey's own validation is the final word and every corridor is proven with a
     * real payout before an admin enables it. Rails that publish no field list (interac, pix, upi) are not offered.
     *
     * @return array{currency: string, method: string, fields: list<string>, optional: list<string>}|null null = Grey cannot take this country (yet)
     */
    public function formFor(string $country): ?array
    {
        $country = strtoupper($country);
        $corridor = $this->countries()[$country] ?? null;
        if ($corridor === null) {
            return null;
        }
        $mobile = $corridor['method'] === 'mobile_money';
        $method = $this->mapper->methodFor($this->client->payoutMethods(), $corridor['currency'], $country, $mobile);
        if ($method === null || empty($method['required_fields'])) {
            return null;
        }

        $required = $mobile ? ['account_number', 'network_type'] : ['account_number'];
        $optional = [];
        foreach ((array) $method['required_fields'] as $f) {
            $f = (string) preg_replace('/^beneficiary\./', '', (string) $f);
            if (in_array($f, self::USER_FIELDS, true) && ! in_array($f, $required, true)) {
                $optional[] = $f;
            }
        }

        return ['currency' => $corridor['currency'], 'method' => $corridor['method'], 'fields' => $required, 'optional' => array_values(array_unique($optional))];
    }

    /**
     * @param  array<string, string>  $input
     *
     * @throws AccountResolutionException
     */
    public function add(User $user, string $country, array $input): PayoutAccount
    {
        $this->guard($user);
        $country = strtoupper($country);
        $form = $this->formFor($country);
        if ($form === null) {
            throw new AccountResolutionException("Payouts to {$country} aren't available yet.");
        }

        $clean = [];
        foreach ($form['fields'] as $f) {
            $v = trim((string) ($input[$f] ?? ''));
            if ($v === '' || mb_strlen($v) > 64 || preg_match('/[\x00-\x1f]/', $v)) {
                throw new AccountResolutionException('Please fill in every field: '.str_replace('_', ' ', $f).'.');
            }
            $clean[$f] = $v;
        }
        foreach ($form['optional'] as $f) {
            $v = trim((string) ($input[$f] ?? ''));
            if ($v !== '' && mb_strlen($v) <= 64 && ! preg_match('/[\x00-\x1f]/', $v)) {
                $clean[$f] = $v;
            }
        }
        $number = $clean['account_number'];

        $mobile = $form['method'] === 'mobile_money';

        return DB::transaction(function () use ($user, $country, $form, $clean, $number, $mobile) {
            $isFirst = ! PayoutAccount::query()->where('user_id', $user->id)->exists();
            if ($isFirst) {
                PayoutAccount::query()->where('user_id', $user->id)->update(['is_default' => false]);
            }
            $account = PayoutAccount::create([
                'user_id' => $user->id,
                'type' => $mobile ? 'mobile_money' : 'bank',
                'method' => $form['method'],
                'country' => $country,
                'currency' => $form['currency'],
                'bank_code' => $mobile ? ($clean['network_type'] ?? '') : ($clean['bank_code'] ?? ''),
                'bank_name' => $clean['bank_name'] ?? ($mobile ? 'Mobile money' : 'Bank account'),
                'account_number' => $number,
                'account_name' => $user->name,
                'payee_kyc_name' => $user->name,
                'provider' => 'grey',
                'details' => array_diff_key($clean, array_flip(['account_number', 'bank_code', 'bank_name', 'network_type'])),
                'is_verified' => true,
                'is_default' => $isFirst,
            ]);
            Auditor::log('payout.account_added', 'PayoutAccount', $account->id, ['provider' => 'grey', 'country' => $country]);
            if (PayoutSettings::notifyOnRequest()) {
                $user->notify(new \App\Notifications\PayoutRequestedNotice(null, 'account'));
            }

            return $account;
        });
    }

    /** A Grey Tag as people type it: letters, digits, dot, underscore, dash; a leading @ is dropped. */
    public static function cleanTag(string $tag): string
    {
        return strtolower(ltrim(trim($tag), '@'));
    }

    /**
     * Resolve a Grey Tag against Grey itself: the one account-name check Grey's API offers. Read-only.
     *
     * @return array{tag: string, first_name: string, last_name: string, name: string}
     *
     * @throws AccountResolutionException
     */
    public function resolveTag(string $input): array
    {
        $tag = self::cleanTag($input);
        if (! preg_match('/^[a-z0-9._-]{2,32}$/', $tag)) {
            throw new AccountResolutionException('Enter the Grey Tag exactly as it shows in the Grey app, for example @yourname.');
        }
        try {
            $r = $this->client->greyTag($tag);
        } catch (\Throwable) {
            throw new AccountResolutionException("We can't reach Grey right now. Please try again shortly.");
        }
        if ($r->status() >= 500 || $r->status() === 401 || $r->status() === 403 || $r->status() === 429) {
            throw new AccountResolutionException("We can't confirm this Grey Tag right now. Please try again shortly.");
        }
        if (! $r->successful() || data_get($r->json(), 'data.is_valid') !== true) {
            throw new AccountResolutionException('No Grey account has that tag. Check the spelling in the Grey app.');
        }
        $first = trim((string) data_get($r->json(), 'data.first_name', ''));
        $last = trim((string) data_get($r->json(), 'data.last_name', ''));
        if ($first === '' && $last === '') {
            throw new AccountResolutionException('We could not confirm who owns that tag.');
        }

        return ['tag' => $tag, 'first_name' => $first, 'last_name' => $last, 'name' => trim($first.' '.$last)];
    }

    /** The tag must belong to the member: every word of the Grey name has to appear in the name on their Naara account. */
    public static function nameMatches(string $greyName, string $accountName): bool
    {
        $norm = fn (string $v) => preg_split('/[^a-z0-9]+/', strtolower(\Illuminate\Support\Str::ascii($v)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $theirs = $norm($accountName);
        $words = $norm($greyName);

        return $words !== [] && count(array_diff($words, $theirs)) === 0;
    }

    /**
     * Save a Grey Tag as a payout account: instant, free of bank rails, paid from the USD wallet by P2P transfer.
     * The tag is resolved by Grey and must belong to the member (name match), so money can never be pointed at someone else's tag.
     *
     * @throws AccountResolutionException
     */
    public function addTag(User $user, string $input): PayoutAccount
    {
        $this->guard($user);
        $found = $this->resolveTag($input);
        if (! self::nameMatches($found['name'], (string) $user->name)) {
            throw new AccountResolutionException('That Grey Tag is registered to a different name than your account. Use your own Grey Tag, or update the name on your account first.');
        }
        $country = strtoupper((string) ($user->country ?? ''));
        $country = preg_match('/^[A-Z]{2}$/', $country) ? $country : 'US';

        return DB::transaction(function () use ($user, $found, $country) {
            $isFirst = ! PayoutAccount::query()->where('user_id', $user->id)->exists();
            $account = PayoutAccount::create([
                'user_id' => $user->id,
                'type' => 'grey_tag',
                'method' => 'grey_tag',
                'country' => $country,
                'currency' => 'USD',
                'bank_code' => '',
                'bank_name' => 'Grey Tag',
                'account_number' => '@'.$found['tag'],
                'account_name' => $found['name'],
                'payee_kyc_name' => $user->name,
                'provider' => 'grey',
                'details' => ['grey_tag' => $found['tag']],
                'is_verified' => true,
                'is_default' => $isFirst,
            ]);
            Auditor::log('payout.account_added', 'PayoutAccount', $account->id, ['provider' => 'grey', 'method' => 'grey_tag']);

            return $account;
        });
    }

    private function guard(User $user): void
    {
        if (PayoutFreeze::isFrozen($user->id)) {
            throw new AccountResolutionException('Payouts on this account are paused for your protection. Please contact support.');
        }
        $limit = PayoutSettings::addAccountPerHour();
        if ($limit > 0 && ! RateLimiter::attempt('payout-add-account:'.$user->id, $limit, fn () => true, 3600)) {
            throw new AccountResolutionException("You've added several accounts in a short time. Please wait a while before adding another.");
        }
        app(StepUpAuth::class)->assertFresh($user);
    }
}
