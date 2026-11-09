<?php

namespace App\Services\Topup\Concerns;

use App\Models\TopupProviderLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Every supplier call goes through here: timed, redacted (api keys, secrets, bearer tokens, passwords never reach the table)
 * and written to topup_provider_logs for disputes. Logging can never break a money path.
 */
trait LogsProviderCalls
{
    /** @var list<string> */
    private static array $redactKeys = ['api-key', 'secret-key', 'public-key', 'client_secret', 'client_id', 'authorization', 'access_token', 'token', 'password', 'api_key', 'apikey'];

    /**
     * @param  array<string, mixed>  $body
     */
    protected function send(PendingRequest $client, string $method, string $path, array $body = [], ?string $reference = null): Response
    {
        $t0 = microtime(true);
        $status = null;
        $resp = null;
        try {
            $r = strtoupper($method) === 'GET' ? $client->get($path, $body) : $client->send(strtoupper($method), $path, ['json' => $body]);
            $status = $r->status();
            $resp = $r->body();

            return $r;
        } catch (Throwable $e) {
            $resp = 'EXCEPTION: '.substr($e->getMessage(), 0, 300);
            throw $e;
        } finally {
            $this->writeLog(strtoupper($method).' '.$path, $reference, $body, (string) $resp, $status, (int) round((microtime(true) - $t0) * 1000));
        }
    }

    /** @param  array<string, mixed>  $request */
    protected function writeLog(string $endpoint, ?string $reference, array $request, string $response, ?int $status, int $ms): void
    {
        try {
            TopupProviderLog::create([
                'provider_code' => $this->code(), 'endpoint' => substr($endpoint, 0, 160), 'reference' => $reference,
                'request' => $request ? substr((string) json_encode(self::redact($request)), 0, 4000) : null,
                'response' => substr(self::redactText($response), 0, 6000), 'http_status' => $status, 'duration_ms' => $ms, 'created_at' => now(),
            ]);
        } catch (Throwable) {
            // logging must never break a purchase
        }
    }

    /** @param  array<string, mixed>  $data  @return array<string, mixed> */
    public static function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (in_array(strtolower((string) $k), self::$redactKeys, true)) {
                $data[$k] = '[redacted]';
            } elseif (is_array($v)) {
                $data[$k] = self::redact($v);
            }
        }

        return $data;
    }

    public static function redactText(string $text): string
    {
        $j = json_decode($text, true);
        if (is_array($j)) {
            return (string) json_encode(self::redact($j));
        }

        return preg_replace('/(access_token|api[-_]?key|secret[-_]?key|client_secret)"?\s*[:=]\s*"?[A-Za-z0-9._\-]{8,}/i', '$1=[redacted]', $text) ?? $text;
    }
}
