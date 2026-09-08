<?php

namespace App\Services\SMS;

use App\Exceptions\GetatextAuthException;
use App\Exceptions\LowBalanceException;
use App\Exceptions\MaintenanceException;
use App\Exceptions\OutOfStockException;
use App\Exceptions\SmsException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Getatext — US numbers (blueprint Section 8). Real non-VoIP US SIM, SMS only
 * (no voice). Auth via the `Auth: {api_key}` header. Prices are fetched live;
 * always pass max_price so a carrier/area-code filter can't erase margin.
 * Getatext error strings are mapped onto the shared lane exceptions (8.3).
 */
class GetatextService implements SmsProviderInterface
{
    private function client(): PendingRequest
    {
        // Bounded so a slow/dead provider can't hang the worker + session lock.
        // 3s connect, 8s total; purchase calls override to 15s.
        return Http::baseUrl(rtrim(config('services.getatext.base_url'), '/'))
            ->timeout(8)->connectTimeout(3)
            ->withHeaders(['Auth' => config('services.getatext.api_key')])
            ->acceptJson()
            ->asJson();
    }

    public function priceFor(string $country, string $service, ?string $operator = null): float
    {
        $items = $this->client()->get('/prices-info')->throw()->json() ?? [];

        foreach ($items as $item) {
            if (($item['api_name'] ?? null) === $service || ($item['service_name'] ?? null) === $service) {
                if ((int) ($item['stock'] ?? 0) <= 0) {
                    throw new OutOfStockException("Getatext out of stock for {$service}.");
                }

                return (float) ($item['price'] ?? 0);
            }
        }

        throw new OutOfStockException("Getatext has no service {$service}.");
    }

    public function buyOtp(string $country, string $service, array $options = []): array
    {
        $payload = array_filter([
            'service' => $service,
            'max_price' => $options['max_price'] ?? null,
            'carrier' => $options['carrier'] ?? null,
            'area_codes' => $options['area_codes'] ?? null,
        ], fn ($v) => $v !== null);

        // Purchase call — longer 15s ceiling for a real buy.
        $json = $this->guard($this->client()->timeout(15)->post('/rent-a-number', $payload)->json());

        return [
            'provider_ref' => (string) $json['id'],
            'number' => (string) $json['number'],
            'cost' => (float) ($json['price'] ?? 0),
            'status' => OtpStatus::PENDING,
        ];
    }

    public function buyRental(string $country, string $service, array $options = []): array
    {
        // Purchase call — longer 15s ceiling for a real rental buy.
        $json = $this->guard($this->client()->timeout(15)->post('/long-rentals', array_filter([
            'service' => $service,
            'rental_time' => $options['rental_time'] ?? '1w',
            'auto_renew' => $options['auto_renew'] ?? false,
        ]))->json());

        return [
            'provider_ref' => (string) $json['id'],
            'number' => (string) ($json['number'] ?? ''),
            'cost' => (float) ($json['price'] ?? 0),
            'status' => OtpStatus::PENDING,
        ];
    }

    public function check(string $providerRef): array
    {
        $json = $this->guard($this->client()->post('/rental-status', ['id' => $providerRef])->json());
        $code = $json['code'] ?? null;

        return [
            'status' => $code ? OtpStatus::RECEIVED : OtpStatus::PENDING,
            'code' => $code !== null ? (string) $code : null,
        ];
    }

    public function finish(string $providerRef): void
    {
        $this->client()->post("/rental-status/{$providerRef}/completed");
    }

    public function cancel(string $providerRef): void
    {
        $this->client()->post('/cancel-rental', ['id' => $providerRef]);
    }

    public function balance(): float
    {
        return (float) ($this->client()->get('/balance')->throw()->json()['balance'] ?? 0);
    }

    public function supportsFullRent(): bool
    {
        return false; // Getatext rentals are per-service
    }

    /**
     * Map Getatext error strings (Section 8.3) onto the shared lane exceptions.
     * Returns the payload untouched when there is no error.
     */
    private function guard(?array $json): array
    {
        $json ??= [];
        $error = $json['error'] ?? $json['message'] ?? null;
        if ($error === null) {
            return $json;
        }

        $lower = strtolower($error);

        throw match (true) {
            str_contains($lower, 'api key'), str_contains($lower, 'restricted') => new GetatextAuthException($error),
            str_contains($lower, 'out of stock') => new OutOfStockException($error),
            str_contains($lower, 'maintenance') => new MaintenanceException($error),
            str_contains($lower, 'insufficient') => new LowBalanceException($error),
            default => new SmsException($error),
        };
    }
}
