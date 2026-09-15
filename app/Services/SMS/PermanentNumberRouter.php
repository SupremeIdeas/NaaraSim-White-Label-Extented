<?php

namespace App\Services\SMS;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsException;
use App\Jobs\AlertAdminJob;
use App\Models\Setting;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\Pricing\PricingEngine;
use App\Services\Routing\CandidateOrdering;
use App\Services\Routing\CircuitBreaker;
use App\Services\Wallet\WalletService;
use App\Support\ProviderModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Permanent-number provisioning (Naara Line) — Twilio → Telnyx → Vonage →
 * Sinch → Plivo lane, billed monthly. Money-safety mirrors the eSIM Checkout:
 *   - the FIRST month's retail is debited before we provision; if provisioning
 *     fails the wallet is refunded and the next provider tried,
 *   - if the number is provisioned but the record can't be saved, the number is
 *     released AND the wallet refunded (orphan-charge guard),
 *   - retail always flows through PricingEngine (MarginGuard floor) — a number is
 *     skipped if it can't be sold at cost + minimum profit,
 *   - the provider is never exposed; the user sees only the Model (Naara Line).
 * Recurring monthly charges are handled by RenewVirtualNumbersJob.
 *
 * Prompt 12 — adding a provider to $lane also requires adding it to:
 * `ProviderModels::MODELS['naara_line']['lane']` (+ `PROVIDER_KEY_FIELD`),
 * `ProviderHealth::PROVIDERS`, `SmsInboundWebhookController::PROVIDERS`.
 */
class PermanentNumberRouter
{
    /**
     * Ordered strongest-capability-first: Twilio/Telnyx are the confirmed
     * voice+SMS pair; Vonage next (confirmed Nigeria voice restrictions/
     * features page with real operational content — see VonageService's
     * docblock); Sinch after that (broad but unverified African voice
     * coverage in this environment — treated conservatively); Plivo next
     * (confirmed SMS/number-only — no African inbound voice, see
     * PlivoService::realCapabilities()); Sonetel last (owner audit,
     * 2026-09-15 — the opposite gap: voice + inbound-SMS-routing only, no
     * outbound SMS send API at all, see SonetelService::sendSms()) — the
     * existing VirtualNumber.capabilities gate (SendMessage/MessageSender)
     * already handles a narrow-capability provider safely, same as Plivo.
     *
     * @var list<string>
     */
    protected array $lane = ['twilio', 'telnyx', 'vonage', 'sinch', 'plivo', 'sonetel'];

    public function __construct(
        private readonly WalletService $wallet,
        private readonly PricingEngine $pricing,
        private readonly CircuitBreaker $breaker = new CircuitBreaker,
        private readonly CandidateOrdering $ordering = new CandidateOrdering,
        private readonly NumberBlocklist $blocklist = new NumberBlocklist,
    ) {}

    /** Providers in the lane that actually have keys configured. */
    public function configuredLane(): array
    {
        return array_values(array_filter($this->lane, fn ($p) => $this->isConfigured($p)));
    }

    private function isConfigured(string $provider): bool
    {
        return ProviderModels::providerConfigured($provider);
    }

    /**
     * Search the lane for available numbers, priced at RETAIL (never cost). Uses
     * the first configured provider that returns results.
     *
     * Number matching (roadmap §5/§6) is provider-agnostic: the caller passes a
     * plain `digits` + `position` spec (ends|contains); we translate it to each
     * provider's native filter (Twilio Contains, Telnyx ends_with/contains) AND
     * post-filter the results so the match is accurate regardless of what the
     * provider honours. The provider's own syntax is never exposed to the caller.
     *
     * @param  array{digits?:string, position?:string, limit?:int}  $options
     * @return array{provider: ?string, numbers: array<int, array{number:string, locality:string, monthly_retail:float}>}
     */
    public function search(string $country, array $options = []): array
    {
        $digits = preg_replace('/\D/', '', (string) ($options['digits'] ?? ''));
        $position = ($options['position'] ?? 'ends') === 'contains' ? 'contains' : 'ends';

        // BUILD-15 §4: prefer the fastest/most-reliable configured provider and
        // skip an open circuit — reorder only; the search call itself is unchanged.
        foreach ($this->ordering->order($this->lane, 'permanent') as $provider) {
            if (! $this->isConfigured($provider) || ! $this->breaker->allows($provider)) {
                continue;
            }
            $svc = app("number.{$provider}");
            $found = $svc->searchNumbers($country, $this->providerOptions($provider, $digits, $position, $options));

            // Guarantee the match ourselves — a provider that ignores the filter
            // (or only substring-matches) can't slip a non-matching number through.
            if ($digits !== '') {
                $found = array_values(array_filter(
                    $found,
                    fn ($n) => $this->matchesSpec((string) ($n['number'] ?? ''), $digits, $position),
                ));
            }

            // Recycled-number pre-check (Prompt 11): never re-offer a number we
            // pulled for abuse/complaint, whatever the provider recycles back.
            $blocked = $this->blocklist->blockedAmong(array_map(fn ($n) => (string) ($n['number'] ?? ''), $found));
            if ($blocked !== []) {
                $found = array_values(array_filter(
                    $found,
                    fn ($n) => ! isset($blocked[$this->blocklist->normalize((string) ($n['number'] ?? ''))]),
                ));
            }

            if ($found === []) {
                continue;
            }
            $cost = (float) $svc->monthlyCost($country);
            $retail = $this->pricing->calculateSmsRetail($cost, $provider); // MarginGuard-floored

            return [
                'provider' => $provider,
                'numbers' => array_map(fn ($n) => [
                    'number' => $n['number'],
                    'locality' => $n['locality'] ?? '',
                    'monthly_retail' => round($retail, 2),
                ], $found),
            ];
        }

        return ['provider' => null, 'numbers' => []];
    }

    /** Translate the neutral spec into a provider's native search options. */
    private function providerOptions(string $provider, string $digits, string $position, array $options): array
    {
        $native = ['limit' => (int) ($options['limit'] ?? 10)];
        if ($digits === '') {
            return $native;
        }
        if ($provider === 'telnyx' || $provider === 'vonage') {
            // Telnyx/Vonage both honour the position natively.
            return $native + ($position === 'ends' ? ['ends_with' => $digits] : ['contains' => $digits]);
        }

        // Twilio's Contains does a pattern/substring match; we post-filter for
        // the exact position, so passing the digits as `contains` is enough.
        // Sinch/Plivo are treated the same way for the same reason.
        return $native + ['contains' => $digits];
    }

    /** Does a number's digits satisfy the neutral match spec? */
    private function matchesSpec(string $number, string $digits, string $position): bool
    {
        $num = preg_replace('/\D/', '', $number);

        return $position === 'ends' ? str_ends_with($num, $digits) : str_contains($num, $digits);
    }

    /**
     * Provision a specific number from a specific (server-chosen) provider. The
     * provider MUST come from a prior search() on the server, never the client.
     */
    public function provision(User $user, string $country, string $number, string $provider): VirtualNumber
    {
        if (! in_array($provider, $this->lane, true) || ! $this->isConfigured($provider)) {
            throw new SmsException('That number is no longer available.');
        }

        // Recycled-number pre-check (Prompt 11): refuse a blocked number before
        // any charge — defence in depth behind the search-time filter, in case a
        // stale/forged number reaches provision().
        if ($this->blocklist->isBlocked($number)) {
            throw new SmsException('That number is no longer available.');
        }

        $svc = app("number.{$provider}");
        $cost = (float) $svc->monthlyCost($country);
        $minProfit = (float) Setting::getValue('pricing.sms_min_profit', 0.01);
        $retail = round($this->pricing->calculateSmsRetail($cost, $provider), 4);

        // MarginGuard backstop (calculateSmsRetail already floors, but never trust).
        if ($retail < $cost + $minProfit) {
            throw new SmsException('This number can’t be offered right now.');
        }

        $ref = "vnum:{$user->id}:".preg_replace('/\D/', '', $number).':'.now()->timestamp;

        // 1) Charge the first month up-front (never provision without payment).
        try {
            $this->wallet->debit($user, $retail, 'USD', [
                'reference' => $ref,
                'description' => 'Virtual number (first month)',
            ]);
        } catch (InsufficientBalanceException $e) {
            throw $e; // caller shows "top up"
        }

        // 2) Provision at the provider.
        try {
            $bought = $svc->buyNumber($country, ['number' => $number]);
        } catch (Throwable $e) {
            // A genuine provider provisioning failure — feed the breaker (BUILD-15).
            $this->breaker->record($provider, 'permanent', 'failure', $e->getCode() ? (string) $e->getCode() : class_basename($e), $ref);
            $this->wallet->refund($user, $retail, 'USD', ['reference' => "refund:{$ref}", 'description' => 'Number provisioning failed']);
            Log::warning("PermanentNumberRouter: {$provider} provisioning failed: ".$e->getMessage());
            throw new SmsException('That number could not be reserved — your wallet was refunded.');
        }

        // 3) Persist the subscription. Orphan-charge guard: release + refund on failure.
        try {
            $vnumber = VirtualNumber::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'phone_number' => $bought['number'],
                'sid' => $bought['provider_ref'],
                'capabilities' => $bought['capabilities'] ?? ['sms' => true, 'voice' => true],
                'monthly_cost' => $cost,
                'monthly_retail' => $retail,
                'status' => 'active',
                'next_billing_date' => now()->addMonthNoOverflow()->toDateString(),
                'provisioned_at' => now(),
            ]);
            // Itemised receipt (BUILD-7 §1) for the first month — best-effort.
            \App\Support\PurchaseReceipt::send($user, 'Permanent number (first month)', $retail, $ref);

            $this->breaker->record($provider, 'permanent', 'success', null, $ref);

            return $vnumber;
        } catch (Throwable $e) {
            $svc->releaseNumber($bought['provider_ref']);
            $this->wallet->refund($user, $retail, 'USD', ['reference' => "refund:{$ref}", 'description' => 'Number could not be saved']);
            AlertAdminJob::dispatch(
                code: 'vnum_save_failed',
                message: "Provisioned {$bought['number']} for user {$user->id} but failed to persist; released + refunded.",
                context: ['user_id' => $user->id, 'number' => $bought['number']],
            );
            throw new SmsException('Something went wrong finalising your number — your wallet was refunded.');
        }
    }
}
