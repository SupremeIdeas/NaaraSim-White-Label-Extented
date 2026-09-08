<?php

namespace App\Services\Api;

use App\Exceptions\InsufficientBalanceException;
use App\Models\ApiClient;
use App\Models\ApiWalletTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ApiWalletService — the single owner of Developer API prepaid balance changes
 * (ROADMAP §Layer 2), mirroring WalletService's discipline exactly:
 *
 *   - Atomic: a DB transaction with a pessimistic lockForUpdate on the client
 *     row, always writing an api_wallet_transactions row (balance_before /
 *     after) in the SAME transaction.
 *   - Serialized across processes by an atomic cache lock per client.
 *   - Idempotent: a reference that already posted returns the existing row
 *     instead of moving money twice (money actions never blind-retry).
 *
 * The developer API is USD-only, so there is a single balance column.
 */
class ApiWalletService
{
    private const SCALE = 4;

    /** Top up a client's prepaid balance. */
    public function credit(ApiClient $client, float $amount, array $meta = []): ApiWalletTransaction
    {
        return $this->apply($client, 'credit', $amount, $meta);
    }

    /** Charge a client's prepaid balance. Throws InsufficientBalanceException. */
    public function debit(ApiClient $client, float $amount, array $meta = []): ApiWalletTransaction
    {
        return $this->apply($client, 'debit', $amount, $meta);
    }

    /** Return a previous charge (idempotent). */
    public function refund(ApiClient $client, float $amount, array $meta = []): ApiWalletTransaction
    {
        return $this->apply($client, 'refund', $amount, $meta);
    }

    private function apply(ApiClient $client, string $type, float $amount, array $meta): ApiWalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('API wallet amount must be positive.');
        }
        $amount = round($amount, self::SCALE);
        $reference = $meta['reference'] ?? $meta['idempotency_key'] ?? null;

        return Cache::lock("api-wallet:{$client->id}", 10)->block(5, function () use ($client, $type, $amount, $meta, $reference) {
            return DB::transaction(function () use ($client, $type, $amount, $meta, $reference) {
                if ($reference !== null) {
                    $existing = ApiWalletTransaction::query()
                        ->where('api_client_id', $client->id)
                        ->where('reference', $reference)
                        ->first();
                    if ($existing !== null) {
                        return $existing; // already posted — never double-charge
                    }
                }

                /** @var ApiClient $locked */
                $locked = ApiClient::query()->whereKey($client->id)->lockForUpdate()->first();

                $before = round((float) $locked->prepaid_balance_usd, self::SCALE);
                $delta = $type === 'debit' ? -$amount : $amount;
                $after = round($before + $delta, self::SCALE);

                if ($type === 'debit' && $after < 0) {
                    throw new InsufficientBalanceException($locked->id, 'USD', $amount, $before);
                }

                $locked->prepaid_balance_usd = $after;
                $locked->save();

                return ApiWalletTransaction::create([
                    'api_client_id' => $locked->id,
                    'type' => $type,
                    'amount' => $amount,
                    'currency' => 'USD',
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'reference' => $reference ?? (string) Str::uuid(),
                    'description' => $meta['description'] ?? null,
                    'status' => 'completed',
                ]);
            });
        });
    }
}
