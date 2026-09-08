<?php

namespace App\Livewire;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsException;
use App\Jobs\PollSmsOtpJob;
use App\Models\SmsOrder;
use App\Models\VirtualNumber;
use App\Models\WizardSession;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\PermanentNumberRouter;
use App\Services\SMS\SmsNumberRouter;
use App\Services\Wallet\WalletService;
use App\Services\Wizard\WizardIntent;
use App\Support\Niche\DeviceCompat;
use App\Support\ProviderModels;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The NaaraSim Wizard (roadmap §3) — a guided, buttons-only purchase widget.
 *
 * It is a deterministic state machine over the Model registry (ProviderModels)
 * and the real engines (SmsNumberRouter, PermanentNumberRouter, WalletService),
 * so it is fully usable with no LLM in the loop (Claude NLU is a later polish).
 *
 * Money-safety mirrors the dedicated flows exactly:
 *   - every quote/charge runs through the routers + PricingEngine (retail only),
 *   - the wallet is debited before ordering; the router refunds on failure,
 *   - a short wallet routes to a top-up state — it never charges.
 * Supplier masking: NO provider or cost is ever kept in a PUBLIC property (those
 * are dehydrated into the browser snapshot). The wizard stores only the public
 * Model key + retail; the real provider is re-derived server-side at purchase.
 */
class Wizard extends Component
{
    /** Whether the floating panel is open. */
    public bool $open = false;

    /** State: purpose → country → (service|device|pick) → review → result. */
    public string $step = 'purpose';

    /**
     * Per-step "visit" counters + last rendered step (wizard picker fix). Each
     * fresh entry into the country/service step bumps its counter, which keys the
     * step's Alpine container so Livewire's DOM-morph gives a genuinely new node
     * — Alpine then re-initialises `cq`/`sq` to '' instead of retaining a stale
     * search string that could hide every item after a forward→back→forward.
     */
    public array $stepVisits = ['country' => 0, 'service' => 0];

    public ?string $lastStep = null;

    /** Manual escape hatch (refresh buttons) — bumps the key to force a clean re-init. */
    public function refreshCountries(): void
    {
        $this->stepVisits['country'] = ($this->stepVisits['country'] ?? 0) + 1;
    }

    public function refreshServices(): void
    {
        $this->stepVisits['service'] = ($this->stepVisits['service'] ?? 0) + 1;
    }

    /** Public Model key the user is buying (naara_verify|naara_rent|naara_line|naara_data). */
    public ?string $model = null;

    public ?string $country = null;

    public ?string $service = null;

    /** eSIM device-compat gate (roadmap §4). */
    public string $device = '';

    public ?bool $deviceResult = null;

    /** Retail-only quote shown before confirming (never cost). */
    public ?float $quoteRetail = null;

    /** Permanent-number candidates from a server search: [{number, locality, monthly_retail}]. */
    public array $candidates = [];

    /**
     * Number matching for Naara Line (roadmap §5). The user's desired digits and
     * where they should fall (ends|contains). These are the user's own input (not
     * a supplier detail), so they're safe as public state.
     */
    public string $matchDigits = '';

    public string $matchPosition = 'ends';

    /** Whether the current pick list came from a pattern search (drives copy). */
    public bool $matchUsed = false;

    /** The completed order (surfaced for OTP polling / copy). */
    public ?int $smsOrderId = null;

    public ?int $virtualNumberId = null;

    public ?string $error = null;

    public ?string $notice = null;

    /** Optional free-text box (Claude sprinkle, roadmap §8) — buttons still rule. */
    public string $freeText = '';

    /**
     * OTP push-to-widget (roadmap §3.10). The widget surfaces the user's latest
     * live OTP from ANYWHERE (wizard or the dedicated numbers page), so the code
     * lands here with one-tap copy. `dismissedOtpId` hides everything up to and
     * including an order the user has finished with (ids only ever increase).
     */
    public int $dismissedOtpId = 0;

    /**
     * The FULL country + service catalogue (static base + live provider sync) —
     * never a curated handful. Sourced from NumberCatalogue and passed to the
     * view at render time, so the Livewire snapshot stays lean.
     *
     * @return array<string, string> slug => label
     */
    private function catalogueCountries(): array
    {
        return \App\Support\NumberCatalogue::countries();
    }

    /** @return list<string> service slugs */
    private function catalogueServiceSlugs(): array
    {
        return array_keys(\App\Support\NumberCatalogue::services());
    }

    /** Model key → number type for the routers (eSIM has no number type). */
    private const MODEL_TYPE = [
        'naara_verify' => NumberRequest::TYPE_OTP,
        'naara_rent' => NumberRequest::TYPE_RENTAL,
        'naara_line' => NumberRequest::TYPE_PERMANENT,
    ];

    /**
     * eSIM Models — routed through the device-compat gate + guided catalogue
     * hand-off, NEVER the number routers. naara_connect is the voice+data eSIM
     * (ProviderRouter's $voiceChain); it is an eSIM product, so it MUST follow
     * the same path as naara_data and never reach SmsNumberRouter (which would
     * throw "Unknown number type" on its undefined MODEL_TYPE entry).
     */
    private const ESIM_MODELS = ['naara_data', 'naara_connect'];

    private function isEsimModel(?string $model): bool
    {
        return in_array($model, self::ESIM_MODELS, true);
    }

    public function mount(): void
    {
        $this->restore();
    }

    /**
     * The purposes the user can pick — built ONLY from Models that are actually
     * available right now (roadmap §2.4), so a supplier-less capability never
     * appears. Each entry is public-safe (no provider/cost).
     *
     * @return array<int, array{key:string, name:string, tagline:string, icon:string, purpose:string}>
     */
    #[Computed]
    public function purposes(): array
    {
        $labels = [
            'naara_verify' => 'Get a verification code',
            'naara_rent' => 'Rent a number',
            'naara_line' => 'Get a permanent number + calls',
            'naara_data' => 'Get eSIM data',
            'naara_connect' => 'Get a full eSIM (calls + data)',
        ];

        return array_map(fn ($m) => [
            'key' => $m['key'],
            'name' => $m['name'],
            'tagline' => $m['tagline'],
            'icon' => $m['icon'],
            'purpose' => $labels[$m['key']] ?? $m['name'],
        ], ProviderModels::available());
    }

    /**
     * The user's latest surfaceable OTP (roadmap §3.10) — waiting for a code or
     * freshly arrived, from any entry point, within the last 30 minutes and not
     * yet dismissed. Supplier stays masked (SmsOrder hides `provider`).
     */
    #[Computed]
    public function liveOtp(): ?SmsOrder
    {
        return SmsOrder::where('user_id', auth()->id())
            ->where('type', NumberRequest::TYPE_OTP)
            ->whereIn('status', ['pending', 'waiting', 'completed'])
            ->where('id', '>', $this->dismissedOtpId)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest('id')
            ->first();
    }

    /** True only while a surfaced code is still being fetched (bounds polling). */
    #[Computed]
    public function otpPending(): bool
    {
        return ($otp = $this->liveOtp) !== null && in_array($otp->status, ['pending', 'waiting'], true);
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
        // Opening with a live OTP and no purchase mid-flight → jump to the code.
        if ($this->open && $this->liveOtp && in_array($this->step, ['purpose', 'result'], true)) {
            $this->step = 'otp';
        }
    }

    /** Open the wizard from elsewhere (the floating-nav centerpiece merges it in). */
    #[\Livewire\Attributes\On('open-wizard')]
    public function openFromNav(): void
    {
        $this->open = true;
        if ($this->liveOtp && in_array($this->step, ['purpose', 'result'], true)) {
            $this->step = 'otp';
        }
    }

    /** Open the widget straight to the surfaced OTP (launcher "code ready" tap). */
    public function openOtp(): void
    {
        $this->open = true;
        $this->step = 'otp';
    }

    /** Finish with the surfaced OTP — hide it (and anything older) from the widget. */
    public function dismissOtp(): void
    {
        if ($otp = $this->liveOtp) {
            $this->dismissedOtpId = $otp->id;
        }
        unset($this->liveOtp, $this->otpPending);
        $this->reset('smsOrderId', 'notice');
        $this->step = 'purpose';
    }

    /** Request another OTP — reuse the country if we still have it, else restart. */
    public function anotherOtp(): void
    {
        if ($otp = $this->liveOtp) {
            $this->dismissedOtpId = $otp->id;
        }
        unset($this->liveOtp, $this->otpPending);
        $this->reset('smsOrderId', 'notice', 'error', 'quoteRetail');
        $this->model = 'naara_verify';
        $this->step = $this->country ? 'service' : 'country';
    }

    /** Whether the free-text helper is offered (an Anthropic key is configured). */
    #[Computed]
    public function nluOn(): bool
    {
        return app(WizardIntent::class)->available();
    }

    /** The Wizard convenience fee for THIS user's next purchase (roadmap §6). */
    #[Computed]
    public function wizardFee(): float
    {
        return \App\Support\WizardFee::forUser(auth()->user());
    }

    // ---- navigation --------------------------------------------------------

    /**
     * Claude sprinkle (roadmap §8): map the free-text box to fixed options and
     * advance the SAME deterministic machine the buttons drive. Claude only
     * pre-selects Model/country/service (all whitelisted); it never buys — the
     * furthest it goes is a read-only quote, and the user still taps to pay.
     */
    public function interpret(WizardIntent $intent, SmsNumberRouter $router): void
    {
        $this->error = null;
        $this->notice = null;
        $text = trim($this->freeText);
        if ($text === '') {
            return;
        }

        $parsed = $intent->parse($text, array_column($this->purposes(), 'key'), $this->catalogueCountries(), $this->catalogueServiceSlugs());
        $this->freeText = '';

        if (! $parsed || empty($parsed['model'])) {
            $this->notice = 'I didn’t quite catch that — tap an option below to continue.';

            return;
        }

        $this->resetFlow();
        $this->model = $parsed['model'];

        if (! empty($parsed['country'])) {
            $this->country = $parsed['country'];
            if ($this->isEsimModel($this->model)) {
                $this->step = 'device';
            } elseif ($this->model === 'naara_line') {
                $this->step = 'match';
            } elseif (! empty($parsed['service'])) {
                $this->service = $parsed['service'];
                $this->quote($router); // read-only — no money
            } else {
                $this->step = 'service';
            }
        } else {
            $this->step = 'country';
        }
        $this->persist();
    }

    public function choosePurpose(string $modelKey): void
    {
        $this->resetFlow();
        // Only ever accept a Model that is genuinely available (server-verified).
        if (! collect($this->purposes())->contains(fn ($p) => $p['key'] === $modelKey)) {
            $this->error = 'That option isn’t available right now.';

            return;
        }
        $this->model = $modelKey;
        $this->step = 'country';
        $this->persist();
    }

    public function chooseCountry(string $slug): void
    {
        if (! isset($this->catalogueCountries()[$slug])) {
            return;
        }
        $this->country = $slug;
        $this->error = null;

        // Branch by the Model's capability. Both eSIM Models (data + full/voice)
        // go through the device-compat gate, never the number routers.
        if ($this->isEsimModel($this->model)) {
            $this->step = 'device';
        } elseif ($this->model === 'naara_line') {
            // Naara Line supports number matching (roadmap §5): let the user shape
            // the number before we search, or skip straight to any available one.
            $this->step = 'match';
        } else {
            $this->step = 'service';
        }
        $this->persist();
    }

    public function chooseService(string $service, SmsNumberRouter $router): void
    {
        if (! in_array($service, $this->catalogueServiceSlugs(), true)) {
            return;
        }
        $this->service = $service;
        $this->quote($router);
    }

    /** OTP/rental live quote (retail only — provider + cost are discarded). */
    private function quote(SmsNumberRouter $router): void
    {
        $this->error = null;
        try {
            $q = $router->quote(new NumberRequest($this->country, self::MODEL_TYPE[$this->model], $this->service, auth()->user()));
        } catch (SmsException $e) {
            $this->error = 'No number is available for that country and service right now. Try another.';
            $this->step = 'service';

            return;
        }
        $this->quoteRetail = round((float) $q['retail'], 2); // NEVER store cost/provider
        $this->step = 'review';
        $this->persist();
    }

    // ---- eSIM path (guided hand-off to the tested checkout) -----------------

    public function checkDevice(): void
    {
        $this->deviceResult = DeviceCompat::check($this->device);
    }

    /**
     * eSIM purchase is completed in the dedicated, fully-tested Checkout (coupons,
     * credits, orphan-charge guard). The wizard guides the user there filtered by
     * country rather than duplicating that money path (roadmap principle 3).
     */
    public function goToEsims()
    {
        $label = $this->catalogueCountries()[$this->country] ?? '';
        $params = ['q' => $label];
        // naara_connect is the voice+data eSIM — land the user on the Naara
        // Connect (Full) tab so they see voice-capable plans, not data-only.
        if ($this->model === 'naara_connect') {
            $params['tab'] = 'full';
        }
        $this->finish();

        return $this->redirect(route('catalogue', $params), navigate: true);
    }

    // ---- permanent path -----------------------------------------------------

    /** The neutral match spec sent to the router (digits + position). */
    private function matchSpec(): array
    {
        $digits = substr(preg_replace('/\D/', '', $this->matchDigits), -7); // last 4–7 digits
        $position = $this->matchPosition === 'contains' ? 'contains' : 'ends';

        return ['digits' => $digits, 'position' => $position];
    }

    /** Search for a number matching the typed pattern (roadmap §5/§6). */
    public function findNumbers(): void
    {
        $this->matchDigits = trim($this->matchDigits);
        if (preg_replace('/\D/', '', $this->matchDigits) === '') {
            $this->error = 'Type a few digits you’d like (or tap “Show any number”).';

            return;
        }
        $this->matchUsed = true;
        $this->searchPermanent($this->matchSpec());
    }

    /** Skip matching — just show whatever is available for the country. */
    public function showAnyNumber(): void
    {
        $this->reset('matchDigits');
        $this->matchPosition = 'ends';
        $this->matchUsed = false;
        $this->searchPermanent();
    }

    private function searchPermanent(array $spec = []): void
    {
        $this->error = null;
        $found = app(PermanentNumberRouter::class)->search($this->country, $spec);
        // search() returns {provider, numbers[]}; keep ONLY the masked numbers.
        $this->candidates = $found['numbers'];
        $this->step = 'pick';
        if ($this->candidates === []) {
            $this->error = $this->matchUsed
                ? 'No number matched that pattern. Try fewer digits, switch to “Contains”, or show any number.'
                : 'No permanent number is available for that country right now. Try another country.';
        }
    }

    public function provisionPermanent(string $number, PermanentNumberRouter $router, WalletService $wallet): void
    {
        $this->error = null;
        if ($this->rateLimited()) {
            return;
        }

        // Re-derive the provider SERVER-SIDE from a fresh search (never trust the
        // client, never store the provider in a public property). Re-uses the same
        // match spec and re-validates the number is still available (roadmap §7).
        $fresh = $router->search($this->country, $this->matchUsed ? $this->matchSpec() : []);
        $provider = $fresh['provider'];
        $stillThere = collect($fresh['numbers'])->firstWhere('number', $number);
        if (! $provider || ! $stillThere) {
            $this->candidates = $fresh['numbers'];
            $this->error = 'That number was just taken — here are the latest available ones.';

            return;
        }

        $user = auth()->user();

        // Wizard convenience fee (roadmap §6) — taken first, refunded if the
        // provisioning below can't complete, never hidden.
        $fee = $this->wizardFee();
        $feeRef = "wizard-fee:{$user->id}:".now()->timestamp;
        if ($fee > 0) {
            try {
                $wallet->debit($user, $fee, 'USD', ['reference' => $feeRef, 'description' => 'Wizard assist fee']);
            } catch (InsufficientBalanceException $e) {
                $this->step = 'topup';

                return;
            }
        }

        try {
            $vn = $router->provision($user, $this->country, $number, $provider);
        } catch (InsufficientBalanceException $e) {
            if ($fee > 0) {
                $wallet->refund($user, $fee, 'USD', ['reference' => "refund:{$feeRef}", 'description' => 'Wizard fee refunded — number not purchased']);
            }
            $this->step = 'topup';

            return;
        } catch (SmsException $e) {
            if ($fee > 0) {
                $wallet->refund($user, $fee, 'USD', ['reference' => "refund:{$feeRef}", 'description' => 'Wizard fee refunded — number not purchased']);
            }
            $this->error = $e->getMessage();

            return;
        }

        $user->increment('wizard_uses'); // counts toward the free allowance
        $this->virtualNumberId = $vn->id;
        $this->step = 'result';
        $this->notice = 'Your permanent number is live. It’s on your dashboard and renews monthly.';
        $this->clearSession();
        $this->dispatch('nx-toast', variant: 'hero', type: 'success',
            title: 'Number activated',
            message: 'Your new permanent number is ready on your dashboard.');
    }

    // ---- OTP/rental purchase ------------------------------------------------

    public function purchase(WalletService $wallet, SmsNumberRouter $router): void
    {
        $this->error = null;
        if ($this->rateLimited()) {
            return;
        }
        $user = auth()->user();
        $type = self::MODEL_TYPE[$this->model];

        // Re-quote server-side (never trust the displayed retail).
        try {
            $q = $router->quote(new NumberRequest($this->country, $type, $this->service, $user));
        } catch (SmsException $e) {
            $this->error = 'That number just became unavailable. Try another.';
            $this->step = 'service';

            return;
        }
        $retail = round((float) $q['retail'], 4);
        $fee = $this->wizardFee();

        $ref = "wizard-number:{$user->id}:".now()->timestamp;
        try {
            $wallet->debit($user, $retail, 'USD', ['reference' => $ref, 'description' => "Number: {$this->service}"]);
        } catch (InsufficientBalanceException $e) {
            $this->step = 'topup';

            return;
        }

        // Wizard convenience fee (roadmap §6) — charged only alongside a real
        // purchase, refunded with the retail if the order fails, never hidden.
        $feeRef = "wizard-fee:{$user->id}:".now()->timestamp;
        if ($fee > 0) {
            try {
                $wallet->debit($user, $fee, 'USD', ['reference' => $feeRef, 'description' => 'Wizard assist fee']);
            } catch (InsufficientBalanceException $e) {
                $wallet->refund($user, $retail, 'USD', ['reference' => "refund:{$ref}", 'description' => 'Number not purchased']);
                $this->step = 'topup';

                return;
            }
        }

        try {
            // The router refunds the charged amount itself if the whole lane fails.
            $result = $router->order(new NumberRequest($this->country, $type, $this->service, $user, 'USD', $retail));
        } catch (SmsException $e) {
            if ($fee > 0) {
                $wallet->refund($user, $fee, 'USD', ['reference' => "refund:{$feeRef}", 'description' => 'Wizard fee refunded — order failed']);
            }
            $this->error = 'Could not reserve a number — your wallet was refunded.';
            $this->step = 'service';

            return;
        }

        $user->increment('wizard_uses'); // this wizard purchase counts toward the free allowance
        PollSmsOtpJob::dispatch($result->order->id, 'USD');
        $this->smsOrderId = $result->order->id;
        if ($type === NumberRequest::TYPE_OTP) {
            // Surface it through the live-OTP channel (poll → one-tap copy).
            $this->dismissedOtpId = (int) $result->order->id - 1;
            unset($this->liveOtp, $this->otpPending);
            $this->step = 'otp';
            $this->notice = 'Number reserved — we’re fetching your code now.';
        } else {
            $this->step = 'result';
            $this->notice = 'Your rental number is ready.';
        }
        $this->clearSession();
        $this->dispatch('nx-toast', variant: 'hero', type: 'success',
            title: 'Number reserved', message: 'Your number is on its way — check the widget.');
    }

    /**
     * Order rate limit: 10/min (blueprint Section 19.2). Shares the `orders:`
     * key with GetNumber / Checkout so the cap is unified across every purchase
     * entry point — the wizard can't be used to bypass it.
     */
    private function rateLimited(): bool
    {
        $key = 'orders:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->error = 'Too many requests in a short time. Please wait a minute and try again.';

            return true;
        }
        RateLimiter::hit($key, 60);

        return false;
    }

    // ---- housekeeping -------------------------------------------------------

    public function back(): void
    {
        $this->error = null;
        $this->step = match ($this->step) {
            'country' => 'purpose',
            'service', 'device', 'match' => 'country',
            'pick' => 'match',
            'review' => 'service',
            'topup' => $this->model === 'naara_line' ? 'pick' : 'review',
            default => 'purpose',
        };
        $this->persist();
    }

    public function restart(): void
    {
        $this->resetFlow();
        $this->step = 'purpose';
        $this->clearSession();
    }

    private function resetFlow(): void
    {
        $this->reset('model', 'country', 'service', 'device', 'deviceResult',
            'quoteRetail', 'candidates', 'matchDigits', 'matchPosition', 'matchUsed',
            'smsOrderId', 'virtualNumberId', 'error', 'notice');
    }

    private function finish(): void
    {
        $this->clearSession();
    }

    // ---- session persistence (roadmap §7) -----------------------------------

    private function persist(): void
    {
        WizardSession::updateOrCreate(
            ['user_id' => auth()->id()],
            ['step' => $this->step, 'data' => [
                'model' => $this->model, 'country' => $this->country, 'service' => $this->service,
            ]],
        );
    }

    private function restore(): void
    {
        $s = WizardSession::where('user_id', auth()->id())->first();
        if (! $s) {
            return;
        }
        // Only rehydrate a real in-progress selection (never a stale terminal step).
        if (in_array($s->step, ['country', 'service', 'device', 'review'], true) && ! empty($s->data['model'])) {
            $this->model = $s->data['model'] ?? null;
            $this->country = $s->data['country'] ?? null;
            $this->service = $s->data['service'] ?? null;
            $this->step = $s->step;
        }
    }

    private function clearSession(): void
    {
        WizardSession::where('user_id', auth()->id())->delete();
    }

    public function render()
    {
        // Wizard picker fix: bump the visit counter on each genuine ENTRY into
        // the country/service step (a step transition, not a same-step re-render),
        // so the step's wire:key changes and Alpine re-initialises cleanly.
        if ($this->step !== $this->lastStep) {
            if (array_key_exists($this->step, $this->stepVisits)) {
                $this->stepVisits[$this->step]++;
            }
            $this->lastStep = $this->step;
        }

        // smsOrderId / virtualNumberId are public (attacker-settable) properties,
        // so both lookups MUST be scoped to the owner — the view renders the phone
        // number and OTP code, and an unscoped find() would disclose another
        // user's SMS verification code (account-takeover grade).
        $order = $this->smsOrderId
            ? SmsOrder::where('user_id', auth()->id())->find($this->smsOrderId)
            : null;
        $vnumber = $this->virtualNumberId
            ? VirtualNumber::where('user_id', auth()->id())->find($this->virtualNumberId)
            : null;
        $balance = (float) (auth()->user()?->wallet?->usd_balance ?? 0);

        return view('livewire.wizard', [
            'order' => $order,
            'vnumber' => $vnumber,
            'balance' => $balance,
            'countries' => $this->catalogueCountries(),
            'services' => $this->catalogueServiceSlugs(),
        ]);
    }
}
