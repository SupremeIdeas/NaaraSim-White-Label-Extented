<?php

namespace App\Services\Payouts;

use Illuminate\Support\Facades\Http;

/**
 * Flutterwave account resolution + bank list (ROADMAP §Layer 0.1). Flutterwave
 * covers a broad set of African markets, so it's the wider-net resolver behind
 * Paystack. /banks/{country} lists banks by ISO code; /accounts/resolve returns
 * the verified account name for confirmation before we save a destination.
 */
class FlutterwaveBankResolver implements BankResolverInterface
{
    /** Markets Flutterwave transfers/resolution cover (ISO-3166 alpha-2). */
    private const COUNTRIES = ['NG', 'GH', 'KE', 'UG', 'TZ', 'ZA', 'RW', 'ZM', 'CI', 'SN', 'CM', 'EG'];

    public function name(): string
    {
        return 'flutterwave';
    }

    public function available(): bool
    {
        return filled(config('services.flutterwave.secret_key'));
    }

    public function supports(string $country): bool
    {
        return in_array(strtoupper($country), self::COUNTRIES, true);
    }

    public function banks(string $country): array
    {
        if (! $this->available() || ! $this->supports($country)) {
            return [];
        }

        try {
            $response = Http::withToken(config('services.flutterwave.secret_key'))
                ->acceptJson()
                ->get(rtrim(config('services.flutterwave.base_url'), '/').'/banks/'.strtoupper($country))
                ->throw()->json();
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
            $response = Http::withToken(config('services.flutterwave.secret_key'))
                ->acceptJson()
                ->post(rtrim(config('services.flutterwave.base_url'), '/').'/accounts/resolve', [
                    'account_number' => $accountNumber,
                    'account_bank' => $bankCode,
                ])->throw()->json();
        } catch (\Throwable) {
            return null; // fail closed
        }

        $accountName = trim((string) data_get($response, 'data.account_name', ''));
        if ($accountName === '' || data_get($response, 'status') === 'error') {
            return null;
        }

        return new ResolvedAccount(accountName: $accountName, provider: $this->name());
    }
}
