<div class="mx-auto max-w-2xl">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">White-label license</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Run NaaraSim under your own brand. Pick a plan, pay to activate, and upgrade to Extended whenever you're ready.</p>
    </div>

    @if ($error)
        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">{{ $error }}</div>
    @endif

    {{-- Owner request (2026-09-15) — the in-app step-by-step guide. Merchant
         V2 only; opens on whichever step matches where this merchant
         actually is, and surfaces admin-configurable reference links
         (domain/hosting) the moment a hosting choice is made. --}}
    @if ($this->isV2)
        <div class="mb-5 rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10" x-data="{ step: {{ $this->guideCurrentStep }} }">
            <div class="mb-3 flex items-center gap-2">
                <x-icon name="list" class="h-4 w-4 text-primary" />
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Your white-label guide</p>
            </div>
            <div class="space-y-1.5">
                @php
                    $guideSteps = [
                        1 => ['title' => 'Choose & request a plan', 'body' => "Pick a plan below and a hosting preference, then request it. Nothing is charged yet — an admin confirms your price next."],
                        2 => ['title' => 'Pay to activate', 'body' => 'Once your price is confirmed, pay from this page. Your license activates instantly and your feature set unlocks automatically for your tier.'],
                        3 => ['title' => 'Tell us about your project', 'body' => "Submit your brand name, colours, logo, and hosting choice below. If you're self-hosting, sort out your domain/hosting first — see the links here."],
                        4 => ['title' => 'We build your platform', 'body' => "Our team deploys your platform by hand using what you submitted. The progress bar below is our current estimate, not a live tracker — we'll message you on WhatsApp with real updates."],
                        5 => ['title' => 'Log in and go live', 'body' => "You'll get a separate login for your own platform — not this dashboard. Work through the checklist here before you announce you're open."],
                    ];
                @endphp
                @foreach ($guideSteps as $n => $s)
                    <div class="rounded-xl border border-slate-100 dark:border-white/5">
                        <button type="button" @click="step = (step === {{ $n }} ? null : {{ $n }})" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left">
                            <span class="flex items-center gap-2">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ $n <= $this->guideCurrentStep ? 'bg-primary text-white' : 'bg-slate-100 text-slate-400 dark:bg-white/10' }} text-[11px] font-semibold">{{ $n }}</span>
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $s['title'] }}</span>
                            </span>
                            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 transition-transform" x-bind:class="step === {{ $n }} ? 'rotate-90' : ''" />
                        </button>
                        <div x-show="step === {{ $n }}" x-transition class="px-3 pb-3 text-xs text-slate-500 dark:text-slate-400">
                            <p>{{ $s['body'] }}</p>

                            @if ($n === 3 && ($this->guideDomainLinks->isNotEmpty() || $this->guideHostingLinks->isNotEmpty()))
                                <div class="mt-2 space-y-2 border-t border-slate-100 pt-2 dark:border-white/5">
                                    @if ($this->guideDomainLinks->isNotEmpty())
                                        <div>
                                            <p class="mb-1 font-medium text-slate-600 dark:text-slate-300">Need a domain?</p>
                                            @foreach ($this->guideDomainLinks as $link)
                                                <a href="{{ $link->url }}" target="_blank" rel="noopener nofollow sponsored" class="flex items-center gap-1.5 text-primary hover:underline">
                                                    <x-icon name="link" class="h-3 w-3 shrink-0" /> {{ $link->label }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($this->guideHostingLinks->isNotEmpty())
                                        <div>
                                            <p class="mb-1 font-medium text-slate-600 dark:text-slate-300">Recommended hosting for your choice:</p>
                                            @foreach ($this->guideHostingLinks as $link)
                                                <a href="{{ $link->url }}" target="_blank" rel="noopener nofollow sponsored" class="flex items-center gap-1.5 text-primary hover:underline">
                                                    <x-icon name="link" class="h-3 w-3 shrink-0" /> {{ $link->label }}
                                                </a>
                                                @if ($link->description)
                                                    <span class="block pl-[18px] text-[11px] text-slate-400">{{ $link->description }}</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                    <p class="text-[10px] italic text-slate-400">Some of these may be affiliate links that support NaaraSim at no extra cost to you.</p>
                                </div>
                            @endif

                            @if ($n === 5)
                                <ul class="mt-2 space-y-1 border-t border-slate-100 pt-2 dark:border-white/5">
                                    <li class="flex items-start gap-1.5"><x-icon name="check" class="mt-0.5 h-3 w-3 shrink-0 text-primary" /> Change the default admin password &amp; enable 2FA</li>
                                    <li class="flex items-start gap-1.5"><x-icon name="check" class="mt-0.5 h-3 w-3 shrink-0 text-primary" /> Add your own Provider API keys (Admin → Provider Keys)</li>
                                    <li class="flex items-start gap-1.5"><x-icon name="check" class="mt-0.5 h-3 w-3 shrink-0 text-primary" /> Confirm your branding &amp; unlocked features match what you bought</li>
                                    <li class="flex items-start gap-1.5"><x-icon name="check" class="mt-0.5 h-3 w-3 shrink-0 text-primary" /> Run one real (or sandbox) purchase before announcing you're open</li>
                                </ul>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @unless ($this->isV2)
        {{-- Visible-but-locked: the same pattern the Merchant-V2 gate itself uses. --}}
        <div class="rounded-2xl border border-slate-200 nx-glass-tile p-6 text-center dark:border-white/10">
            <x-icon name="lock" class="mx-auto mb-3 h-8 w-8 text-slate-400" />
            <p class="font-semibold text-slate-900 dark:text-white">Merchant V2 required</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Upgrade to Merchant V2 to request your own white-label license.</p>
            <a href="{{ route('merchant.dashboard') }}" class="mt-4 inline-flex items-center gap-2 rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary-dark">Go to storefront</a>
        </div>
    @else
        @if ($this->instance)
            {{-- An existing request/instance: show its status and the relevant action. --}}
            <div class="rounded-2xl border border-slate-200 nx-glass-tile p-5 dark:border-white/10">
                <div class="mb-3 flex items-center justify-between">
                    <p class="font-semibold text-slate-900 dark:text-white">{{ $this->instance->licensePlan?->name ?? 'Your license' }}</p>
                    @php
                        $tone = match ($this->instance->status) {
                            'active' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                            'suspended' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                            'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                            default => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
                        };
                    @endphp
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $tone }}">{{ ucfirst($this->instance->status) }}</span>
                </div>

                @if ($this->instance->status === 'pending' && $this->instance->price_usd === null)
                    <p class="text-sm text-slate-500 dark:text-slate-400">Your request is being reviewed. An admin will confirm your price shortly.</p>
                @elseif ($this->instance->status === 'pending' && $this->instance->price_usd !== null)
                    <div class="mb-3 space-y-1 text-sm text-slate-600 dark:text-slate-300">
                        <div class="flex items-center justify-between">
                            <span>License price</span>
                            <span class="font-semibold text-slate-900 dark:text-white">${{ number_format((float) $this->instance->price_usd, 2) }}</span>
                        </div>
                        @if ($this->instance->hasThemeAddon())
                            <div class="flex items-center justify-between">
                                <span>{{ \App\Support\ThemeAddonCatalog::labelFor($this->instance->theme_addon) }}</span>
                                <span class="font-semibold text-slate-900 dark:text-white">${{ number_format((float) $this->instance->theme_addon_price_usd, 2) }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between border-t border-slate-200 pt-1 font-semibold text-slate-900 dark:border-white/10 dark:text-white">
                            <span>Total due</span>
                            <span>${{ number_format((float) $this->totalDue, 2) }}</span>
                        </div>
                    </div>
                    <button type="button" wire:click="payNow" wire:loading.attr="disabled" wire:target="payNow"
                        class="w-full rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="payNow">Pay ${{ number_format((float) $this->totalDue, 2) }} to activate</span>
                        <span wire:loading wire:target="payNow">Processing…</span>
                    </button>
                @elseif ($this->instance->status === 'active')
                    <p class="text-sm text-slate-500 dark:text-slate-400">Tier: <span class="font-medium text-slate-700 dark:text-slate-200">{{ ucfirst($this->instance->tier) }}</span></p>

                    @if ($this->balanceOwed !== null && $this->balanceOwed > 0)
                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                            <p class="mb-2 text-sm text-slate-600 dark:text-slate-300">Complete the remaining <span class="font-semibold text-slate-900 dark:text-white">${{ number_format($this->balanceOwed, 2) }}</span> to unlock Extended — no downtime, your fork keeps its existing token.</p>
                            <button type="button" wire:click="payBalance" wire:loading.attr="disabled" wire:target="payBalance"
                                class="w-full rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary-dark disabled:opacity-60">
                                <span wire:loading.remove wire:target="payBalance">Pay ${{ number_format($this->balanceOwed, 2) }} to upgrade</span>
                                <span wire:loading wire:target="payBalance">Processing…</span>
                            </button>
                        </div>
                    @endif
                @elseif ($this->instance->status === 'rejected')
                    <p class="text-sm text-slate-500 dark:text-slate-400">This request was not approved.</p>
                @endif
            </div>

            {{-- Prompt 21-EXT2 §2/§4 — the post-purchase project intake form,
                 once the license is live. --}}
            @if ($this->instance->status === 'active')
                <div class="mt-4 rounded-2xl border border-slate-200 nx-glass-tile p-5 dark:border-white/10">
                    @if ($this->intake === null)
                        <h2 class="mb-1 font-semibold text-slate-900 dark:text-white">Project commencement form</h2>
                        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Tell us about your white-label project so we can get it live.</p>

                        @if ($intakeError)
                            <div class="mb-3 rounded-lg border border-red-200 bg-red-50 p-2 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">{{ $intakeError }}</div>
                        @endif

                        <div class="space-y-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">What do you want your white-label platform called?</label>
                                <input type="text" wire:model="intakeDesiredBrandName" placeholder="e.g. ConnectNow" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                                @error('intakeDesiredBrandName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">WhatsApp number (so we can reach you when it's live)</label>
                                <input type="text" wire:model="intakeWhatsapp" placeholder="+234…" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                                @error('intakeWhatsapp') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>

                            <div class="border-t border-slate-200 pt-3 dark:border-white/10">
                                <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">Brand identity</p>
                                <p class="mb-3 text-xs text-slate-400">Same two-tone approach Naara itself uses (a primary + an accent colour) drives every themed surface on your white-label — pick yours below.</p>

                                <div class="mb-3 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Primary colour</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" wire:model="intakeBrandPrimaryColor" class="h-9 w-12 rounded border border-slate-200 dark:border-white/10">
                                            <input type="text" wire:model="intakeBrandPrimaryColor" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                                        </div>
                                        @error('intakeBrandPrimaryColor') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Accent colour</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" wire:model="intakeBrandAccentColor" class="h-9 w-12 rounded border border-slate-200 dark:border-white/10">
                                            <input type="text" wire:model="intakeBrandAccentColor" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                                        </div>
                                        @error('intakeBrandAccentColor') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Logo</label>
                                    <input type="file" wire:model="intakeLogoUpload" accept="image/webp,image/jpeg,image/png,image/svg+xml" class="block w-full text-xs text-slate-500 dark:text-slate-400">
                                    @error('intakeLogoUpload') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                    <div wire:loading wire:target="intakeLogoUpload" class="mt-1 text-xs text-slate-400">Uploading…</div>
                                    <p class="mt-2 mb-1 text-xs text-slate-400">Don't have a final logo yet? Link or describe a reference and our design team can create one for you instead:</p>
                                    <textarea wire:model="intakeLogoDesignReference" rows="2" placeholder="e.g. a link to a logo you like, or a description of the style you want" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100"></textarea>
                                    @error('intakeLogoDesignReference') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Site-wide banner design request</label>
                                    <p class="mb-2 text-xs text-slate-400">Tell us how you'd like your platform's banners to look — the same way Naara's own banners match its brand throughout the app.</p>
                                    <input type="file" wire:model="intakeBannerReferenceUpload" accept="image/webp,image/jpeg,image/png" class="mb-2 block w-full text-xs text-slate-500 dark:text-slate-400">
                                    @error('intakeBannerReferenceUpload') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                    <div wire:loading wire:target="intakeBannerReferenceUpload" class="mb-2 text-xs text-slate-400">Uploading…</div>
                                    <textarea wire:model="intakeBannerDesignRequest" rows="2" placeholder="Describe the look and messaging you want your banners to have" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100"></textarea>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Hosting</label>
                                <select wire:model.live="intakeHostingChoice" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                                    <option value="supreme_ideas_server">Host on Supreme Ideas' server</option>
                                    <option value="own_vps">Host on my own VPS (Cloudways)</option>
                                    <option value="own_shared">Host on my own shared hosting (Hostinger/Namecheap)</option>
                                </select>
                            </div>

                            @if ($this->intakeIsSelfHosted)
                                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                                    @if ($intakeHostingChoice === 'own_vps')
                                        Please purchase <strong>Cloudways' Laravel-optimized VPS hosting</strong> and share the login details below so our team can deploy your platform there.
                                    @else
                                        Please purchase a <strong>premium shared hosting plan from Hostinger or Namecheap</strong> and share the login details below so our team can deploy your platform there.
                                    @endif
                                </div>

                                @if ($this->guideHostingLinks->isNotEmpty() || $this->guideDomainLinks->isNotEmpty())
                                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs dark:border-white/5 dark:bg-white/5">
                                        @if ($this->guideHostingLinks->isNotEmpty())
                                            <p class="mb-1 font-medium text-slate-600 dark:text-slate-300">Get hosting here:</p>
                                            @foreach ($this->guideHostingLinks as $link)
                                                <a href="{{ $link->url }}" target="_blank" rel="noopener nofollow sponsored" class="flex items-center gap-1.5 text-primary hover:underline">
                                                    <x-icon name="link" class="h-3 w-3 shrink-0" /> {{ $link->label }}
                                                </a>
                                            @endforeach
                                        @endif
                                        @if ($this->guideDomainLinks->isNotEmpty())
                                            <p class="mb-1 mt-2 font-medium text-slate-600 dark:text-slate-300">Need a domain too?</p>
                                            @foreach ($this->guideDomainLinks as $link)
                                                <a href="{{ $link->url }}" target="_blank" rel="noopener nofollow sponsored" class="flex items-center gap-1.5 text-primary hover:underline">
                                                    <x-icon name="link" class="h-3 w-3 shrink-0" /> {{ $link->label }}
                                                </a>
                                            @endforeach
                                        @endif
                                        <p class="mt-2 text-[10px] italic text-slate-400">Some of these may be affiliate links that support NaaraSim at no extra cost to you.</p>
                                    </div>
                                @endif

                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Host / control panel URL</label>
                                    <input type="text" wire:model="intakeHostingHost" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                                    @error('intakeHostingHost') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Username</label>
                                        <input type="text" wire:model="intakeHostingUsername" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                                        @error('intakeHostingUsername') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Password</label>
                                        <input type="password" wire:model="intakeHostingPassword" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                                        @error('intakeHostingPassword') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Anything else about access (SSH key, 2FA, etc.)?</label>
                                    <textarea wire:model="intakeHostingNotes" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100"></textarea>
                                </div>
                                <label class="flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400">
                                    <input type="checkbox" wire:model="intakeHostingDisclaimerAcknowledged" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                                    Your login details are encrypted and stored securely — only authorized Supreme Ideas Agency staff can access them, solely to deploy your white-label platform.
                                </label>
                            @endif

                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Anything else we should know?</label>
                                <textarea wire:model="intakeAdditionalNotes" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100"></textarea>
                            </div>

                            <button type="button" wire:click="submitIntake" wire:loading.attr="disabled" wire:target="submitIntake"
                                class="w-full rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary-dark disabled:opacity-60">
                                Submit project details
                            </button>
                        </div>
                    @else
                        <h2 class="mb-1 font-semibold text-slate-900 dark:text-white">Project: {{ $this->intake->desired_brand_name }}</h2>
                        @if ($this->intake->status === 'pending')
                            <p class="text-sm text-slate-500 dark:text-slate-400">Submitted — our team is reviewing your project details.</p>
                        @elseif ($this->intake->status === 'seen')
                            <p class="text-sm text-slate-500 dark:text-slate-400">Reviewed — your deployment timeline will be set shortly.</p>
                        @elseif ($this->intake->status === 'in_progress')
                            @php $day = $this->deployDayOf; $pct = $this->deployProgress; @endphp
                            <p class="mb-2 text-sm text-slate-600 dark:text-slate-300">Deployment in progress — Day {{ $day['day'] }} of {{ $day['of'] }}</p>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                                <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $pct }}%"></div>
                            </div>
                            <p class="mt-1 text-right text-xs text-slate-400">{{ $pct }}%</p>
                        @elseif ($this->intake->status === 'completed')
                            <div class="flex items-center gap-2 text-green-700 dark:text-green-300">
                                <x-icon name="badge-check" class="h-5 w-5" />
                                <p class="text-sm font-medium">Your white-label platform is live!</p>
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        @else
            {{-- Owner request (2026-09-15) — optional custom-theme request
                 add-on, billed together with whichever plan is requested
                 below. Defaults to "No custom theme" so a merchant who just
                 wants a normal setup checks out with no extra cost. --}}
            <div class="mb-4 rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Custom theme request <span class="font-normal text-slate-400">(optional)</span></label>
                <p class="mb-3 text-xs text-slate-400">Want your white-label platform's look designed for you instead of the standard setup? Pick a tier below — it's added to your bill when you request a plan. Skip it and you're billed normally, no extra cost.</p>
                <div class="space-y-2">
                    @foreach ($this->themeAddonOptions as $key => $addon)
                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border p-3 text-sm transition {{ $themeAddon === $key ? 'border-primary bg-primary/5' : 'border-slate-200 dark:border-white/10' }}">
                            <span class="flex items-center gap-2">
                                <input type="radio" wire:model="themeAddon" value="{{ $key }}" class="h-4 w-4 border-slate-300 text-primary focus:ring-primary">
                                <span class="text-slate-700 dark:text-slate-200">{{ $addon['label'] }}</span>
                            </span>
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $addon['price'] > 0 ? '$'.number_format($addon['price'], 0) : '$0' }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Prompt 21-EXT §2.1 — swipeable plan carousel. Each card falls back
                 to a tier-tinted gradient (richer for Extended/Extended V2) until
                 an admin uploads real cover art, so nothing ships as a bare box. --}}
            <div class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-3" style="scroll-padding-inline:1rem;">
                @foreach ($this->plans as $plan)
                    @php
                        $open = \App\Models\WhiteLabelLicensePlan::resellOpenForTier($plan->tier);
                        $balance = \App\Models\WhiteLabelLicensePlan::balanceToExtended($plan);
                        $isV2Plan = $plan->support_level === \App\Models\WhiteLabelLicensePlan::SUPPORT_PRIORITY;
                        $gradient = $isV2Plan
                            ? 'from-[#0D1B2A] to-[#3a2f0a]'
                            : ($plan->tier === \App\Models\WhiteLabelInstance::TIER_EXTENDED ? 'from-[#0A6E6E] to-[#7a5b0c]' : 'from-[#0A6E6E] to-[#0A6E6E]/60');
                    @endphp
                    <div wire:key="plan-card-{{ $plan->id }}" class="w-[80%] shrink-0 snap-center overflow-hidden rounded-2xl border border-slate-200 nx-glass-tile sm:w-64 dark:border-white/10 {{ $open ? '' : 'opacity-60' }}">
                        <div class="relative flex h-28 items-end bg-gradient-to-br {{ $gradient }} p-3"
                             @if ($plan->cover_image_url) style="background-image:url('{{ $plan->cover_image_url }}');background-size:cover;background-position:center;" @endif>
                            <span class="inline-flex items-center gap-1 rounded-full bg-black/30 px-2 py-0.5 text-[11px] font-semibold text-white backdrop-blur-sm">
                                <x-icon name="{{ $isV2Plan ? 'shield-check' : ($plan->tier === \App\Models\WhiteLabelInstance::TIER_EXTENDED ? 'star' : 'zap') }}" class="h-3 w-3" />
                                {{ $plan->name }}
                            </span>
                            @unless ($open)
                                <span class="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-amber-500/90 px-2 py-0.5 text-[11px] font-semibold text-white">
                                    <x-icon name="lock" class="h-3 w-3" /> Closed
                                </span>
                            @endunless
                        </div>
                        <div class="p-4">
                            <div class="mb-1 flex items-baseline justify-between">
                                <p class="font-semibold text-slate-900 dark:text-white">{{ $plan->name }}</p>
                                <p class="text-lg font-bold text-slate-900 dark:text-white">${{ number_format((float) $plan->price_usd, 0) }}</p>
                            </div>
                            <p class="mb-2 text-xs text-slate-400">{{ $plan->tagline }}</p>
                            <ul class="mb-3 space-y-1">
                                @foreach (array_slice($plan->features ?? [], 0, 3) as $feature)
                                    <li class="flex items-start gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                                        <x-icon name="check" class="mt-0.5 h-3 w-3 shrink-0 text-primary" /> {{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                            @if ($balance !== null)
                                <p class="mb-3 text-[11px] text-slate-400">Pay ${{ number_format((float) $plan->price_usd, 0) }} now, complete ${{ number_format($balance, 0) }} later to unlock Extended.</p>
                            @endif
                            @if (! $open)
                                <p class="text-xs font-medium text-amber-600 dark:text-amber-400">Temporarily closed for new requests — check back later.</p>
                            @else
                                <button type="button" wire:click="requestLicense({{ $plan->id }})" class="w-full rounded-xl border border-primary px-3 py-2 text-sm font-semibold text-primary transition hover:bg-primary/5">Request this plan</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Plan comparison table --}}
            <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 nx-glass-tile dark:border-white/10">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-white/10">
                            <th class="p-3 font-medium text-slate-400"></th>
                            @foreach ($this->plans as $plan)
                                <th class="p-3 text-center font-semibold text-slate-900 dark:text-white">{{ $plan->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <tr>
                            <td class="p-3 text-slate-500 dark:text-slate-400">Price</td>
                            @foreach ($this->plans as $plan)
                                <td class="p-3 text-center font-semibold text-slate-900 dark:text-white">${{ number_format((float) $plan->price_usd, 0) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="p-3 text-slate-500 dark:text-slate-400">Support</td>
                            @foreach ($this->plans as $plan)
                                <td class="p-3 text-center text-slate-600 dark:text-slate-300">{{ ucfirst($plan->support_level) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="p-3 text-slate-500 dark:text-slate-400">Full platform, unlocked day one</td>
                            @foreach ($this->plans as $plan)
                                <td class="p-3 text-center">
                                    @if ($plan->tier === \App\Models\WhiteLabelInstance::TIER_EXTENDED)
                                        <x-icon name="check" class="mx-auto h-4 w-4 text-primary" />
                                    @else
                                        <x-icon name="x" class="mx-auto h-4 w-4 text-slate-300 dark:text-slate-600" />
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="p-3 text-slate-500 dark:text-slate-400">Pay balance later to reach Extended</td>
                            @foreach ($this->plans as $plan)
                                <td class="p-3 text-center">
                                    @if ($plan->tier === \App\Models\WhiteLabelInstance::TIER_NORMAL)
                                        <x-icon name="check" class="mx-auto h-4 w-4 text-primary" />
                                    @else
                                        <x-icon name="x" class="mx-auto h-4 w-4 text-slate-300 dark:text-slate-600" />
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10">
                <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Hosting preference</label>
                <select wire:model.live="hostingPreference" class="mb-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                    <option value="supreme_ideas_server">Host on Supreme Ideas' server</option>
                    <option value="own_vps">Host on my own VPS (Cloudways)</option>
                    <option value="own_shared">Host on my own shared hosting (Hostinger/Namecheap)</option>
                </select>
                <p class="mb-2 text-xs text-slate-400">This is just your leaning for now — you'll confirm the details (and, if self-hosting, share login access) in the project intake form once your license is active.</p>
                <label class="flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <input type="checkbox" wire:model="disclaimerAcknowledged" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                    I understand hosting responsibilities and support boundaries for my chosen option.
                </label>
            </div>
        @endif
    @endif
</div>
