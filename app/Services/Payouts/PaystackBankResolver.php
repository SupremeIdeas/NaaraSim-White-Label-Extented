<?php

namespace App\Services\Payouts;

use Illuminate\Support\Facades\Http;

/**
 * Paystack account resolution + bank list (ROADMAP §Layer 0.1). Uses the same
 * secret key as the collection gateway. Paystack's /bank endpoint keys on a
 * lowercase country slug, so we map the ISO-3166 code to it for the markets
 * Paystack covers. Resolve returns the verified account name for confirmation.
 */
class PaystackBankResolver implements BankResolverInterface
{
    /** ISO-3166 alpha-2 → Paystack country slug (only its supported markets). */
    private const COUNTRIES = [
        'NG' => 'nigeria', 'GH' => 'ghana', 'ZA' => 'south africa',
        'KE' => 'kenya', 'CI' => "cote d'ivoire", 'EG' => 'egypt',
    ];

    public function name(): string
    {
        return 'paystack';
    }

    public function available(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    public function supports(string $country): bool
    {
        return isset(self::COUNTRIES[strtoupper($country)]);
    }

    public function banks(string $country): array
    {
        if (! $this->available() || ! $this->supports($country)) {
            return [];
        }

        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->acceptJson()
                ->get(rtrim(config('services.paystack.base_url'), '/').'/bank', [
                    'country' => self::COUNTRIES[strtoupper($country)],
                ])->throw()->json();
        } catch (\Throwable) {
            return [];
        }

        return collect(data_get($response, 'data', []))
            ->map(fn ($b) => ['code' => (string) data_get($b, 'code'), 'name' => (string) data_get($b, 'name')])
            ->filter(fn ($b) => $b['code'] !== '')
            ->values()->all();
    }

    public function resolve(string $country, string $bankCode, string $accountNumber): ?ResolvedAccount
    {
        if (! $this->available()) {
            return null;
        }

        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->acceptJson()
                ->get(rtrim(config('services.paystack.base_url'), '/').'/bank/resolve', [
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode,
                ])->throw()->json();
        } catch (\Throwable) {
            return null; // fail closed — never save an unconfirmed account
        }

        $accountName = trim((string) data_get($response, 'data.account_name', ''));
        if ($accountName === '' || data_get($response, 'status') === false) {
            return null;
        }

        return new ResolvedAccount(accountName: $accountName, provider: $this->name());
    }
}
