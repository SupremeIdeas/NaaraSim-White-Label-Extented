<?php

namespace App\Services\Wizard;

use App\Services\AI\AnthropicClient;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The Wizard's "Claude sprinkle" (roadmap §8) — maps a user's free-text request
 * to the wizard's FIXED options (Model / country / service). It is strictly a
 * convenience layer on top of the buttons:
 *
 *   - It lights up ONLY when an Anthropic key is configured; otherwise the wizard
 *     is fully usable with buttons alone (button fallback on no-key OR any error).
 *   - It can only ever return values from the whitelists the caller passes in —
 *     Claude picks AMONG fixed options, it never invents one. Anything off-list
 *     is dropped, so the state machine stays deterministic.
 *   - Results are cached by normalised input (+ the available Models), with a
 *     tiny token budget, so repeat phrasings are free.
 *   - It NEVER touches money: it only pre-selects options; pricing, ordering and
 *     balance stay pure code, and the user still taps to buy.
 */
class WizardIntent
{
    public function __construct(private readonly AnthropicClient $ai)
    {
    }

    /** True when the free-text helper is available (an Anthropic key is set). */
    public function available(): bool
    {
        return $this->ai->enabled();
    }

    /**
     * Map free text to fixed options. Returns null when the helper is off or the
     * call fails (→ buttons), or an array (possibly empty) of whitelisted picks.
     *
     * @param  list<string>  $models  available Model keys
     * @param  array<string, string>  $countries  slug => label
     * @param  list<string>  $services
     * @return array{model?:string, country?:string, service?:string}|null
     */
    public function parse(string $text, array $models, array $countries, array $services): ?array
    {
        $text = trim($text);
        if ($text === '' || $models === [] || ! $this->available()) {
            return null;
        }

        $key = 'wizard-nlu:'.md5(strtolower($text).'|'.implode(',', $models));
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        try {
            $json = $this->ai->completeJson(
                $this->system($models, $countries, $services),
                [['role' => 'user', 'content' => $text]],
                200, // tiny budget — this is a classifier, not a chat
            );
        } catch (Throwable) {
            return null; // button fallback — never blocks the user
        }

        $result = $this->sanitize($json, $models, $countries, $services);
        Cache::put($key, $result, now()->addDay());

        return $result;
    }

    /** Keep only whitelisted values — Claude may not introduce anything new. */
    private function sanitize(array $json, array $models, array $countries, array $services): array
    {
        $out = [];
        $model = strtolower(trim((string) ($json['model'] ?? '')));
        if (in_array($model, $models, true)) {
            $out['model'] = $model;
        }
        $country = strtolower(trim((string) ($json['country'] ?? '')));
        if (isset($countries[$country])) {
            $out['country'] = $country;
        }
        $service = strtolower(trim((string) ($json['service'] ?? '')));
        if (in_array($service, $services, true)) {
            $out['service'] = $service;
        }

        return $out;
    }

    private function system(array $models, array $countries, array $services): string
    {
        $descriptions = [
            'naara_verify' => 'a one-time verification / OTP code',
            'naara_rent' => 'a short-term rental phone number',
            'naara_line' => 'a permanent phone number with calls',
            'naara_data' => 'eSIM mobile data',
            'naara_connect' => 'a full eSIM with both calls and data, not just data',
        ];
        $modelLines = collect($models)
            ->map(fn ($k) => "  - {$k} = ".($descriptions[$k] ?? $k))
            ->implode("\n");

        return <<<SYS
            You classify a NaaraSim user's request into fixed options. Reply with
            ONLY a JSON object and nothing else:
            {"model": "", "country": "", "service": ""}

            Rules:
            - "model" MUST be one of these keys (or "" if unsure):
            {$modelLines}
            - "country" MUST be one of: {$this->list(array_keys($countries))} (or "").
            - "service" MUST be one of: {$this->list($services)} (or "").
            - Never output any value that is not in the lists above. When unsure, use "".
            SYS;
    }

    private function list(array $values): string
    {
        return implode(', ', $values);
    }
}
