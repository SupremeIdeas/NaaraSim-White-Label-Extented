<?php

namespace Tests\Support;

use App\Services\SMS\OtpStatus;
use App\Services\SMS\SmsProviderInterface;
use Throwable;

/**
 * Configurable OTP/rental provider double for SmsNumberRouter and OTP-job
 * tests. Records finish()/cancel() calls so tests can assert 5sim rating
 * discipline (finish on received, cancel on timeout).
 */
class FakeSmsProvider implements SmsProviderInterface
{
    public int $buyCalls = 0;

    public int $finishCalls = 0;

    public int $cancelCalls = 0;

    /** When true, cancel() throws — to prove the refund isn't gated behind it. */
    public bool $cancelThrows = false;

    public bool $fullRent = false;

    /** Options passed to the last buy call, so tests can assert the operator. */
    public array $lastBuyOptions = [];

    /** Country slug returned by cheapestCountryFor (Smart Buy), if set. */
    public ?string $cheapestCountry = null;

    public function __construct(
        private float|Throwable $price = 1.0,
        private array $buyResponse = ['provider_ref' => 'REF-1', 'number' => '15550001111', 'cost' => 1.0, 'status' => OtpStatus::PENDING],
        private array $checkResponse = ['status' => OtpStatus::PENDING, 'code' => null],
        // Optional per-operator breakdown so router->compareOperators can be
        // exercised: [operator => [cost, count, rate]].
        private array $operators = [],
    ) {}

    /** Present only when seeded — mirrors FiveSimService::operators(). */
    public function operators(string $country, string $service): array
    {
        $rows = [];
        foreach ($this->operators as $name => $op) {
            $rows[] = ['operator' => (string) $name, 'cost' => (float) ($op['cost'] ?? 0), 'count' => (int) ($op['count'] ?? 0), 'rate' => (float) ($op['rate'] ?? 0)];
        }

        return $rows;
    }

    public function supportsFullRent(): bool
    {
        return $this->fullRent;
    }

    public function priceFor(string $country, string $service, ?string $operator = null): float
    {
        if ($this->price instanceof Throwable) {
            throw $this->price;
        }

        return $this->price;
    }

    public function buyOtp(string $country, string $service, array $options = []): array
    {
        $this->buyCalls++;
        $this->lastBuyOptions = $options;

        return $this->buyResponse;
    }

    public function cheapestCountryFor(string $service): ?string
    {
        return $this->cheapestCountry;
    }

    public function buyRental(string $country, string $service, array $options = []): array
    {
        $this->buyCalls++;

        return $this->buyResponse;
    }

    public function check(string $providerRef): array
    {
        return $this->checkResponse;
    }

    public function finish(string $providerRef): void
    {
        $this->finishCalls++;
    }

    public function cancel(string $providerRef): void
    {
        $this->cancelCalls++;
        if ($this->cancelThrows) {
            throw new \RuntimeException('provider cancel failed');
        }
    }

    public function balance(): float
    {
        return 100.0;
    }
}
