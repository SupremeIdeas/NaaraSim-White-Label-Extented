<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">NaaraCredits</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">The loyalty currency and rewards economy. Tune it so it always stays profitable — the notes below each field explain how.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{-- Core economy --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <label class="flex items-start justify-between gap-4">
                <span>
                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Rewards programme</span>
                    <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Turn the whole NaaraCredits system on or off.</span>
                </span>
                <x-ui.switch wire:model="enabled" label="Rewards programme" class="mt-1" />
            </label>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Credits per $1</label>
                    <input wire:model="per_usd" type="number" min="1" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">The exchange rate. Default 100 credits = $1. Higher = credits feel “bigger” but are worth less each.</p>
                    @error('per_usd') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Max % of a purchase payable by credits</label>
                    <input wire:model="max_redeem_pct" type="number" min="0" max="100" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Credits can only cover this share of any order — you always collect real money on top, and the price can never fall below wholesale + profit.</p>
                    @error('max_redeem_pct') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>
        </section>

        {{-- Earn amounts --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-4 text-sm font-semibold text-slate-800 dark:text-slate-100">How users earn</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Sign-up bonus (credits)</label>
                    <input wire:model="signup_bonus" type="number" min="0" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Granted once, on registration. 0 = off.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">First-purchase bonus (credits)</label>
                    <input wire:model="first_purchase_bonus" type="number" min="0" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Granted once, after the first eSIM or number order.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Daily check-in (credits)</label>
                    <input wire:model="checkin_daily" type="number" min="0" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">A small daily habit reward. 0 = off.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Check-in cooldown (hours)</label>
                    <input wire:model="checkin_cooldown_hours" type="number" min="1" max="168" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">How long between check-ins. 24 = once a day.</p>
                </div>
            </div>
        </section>

        {{-- Rewarded ads --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <label class="flex items-start justify-between gap-4">
                <span>
                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Watch &amp; earn (rewarded ads)</span>
                    <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Opt-in only — ads appear solely in the Rewards area, never elsewhere on the platform.</span>
                </span>
                <x-ui.switch wire:model="ads_enabled" label="Rewarded ads" class="mt-1" />
            </label>

            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                <strong>Use a rewarded-ad / offerwall network, NOT Google AdSense.</strong> AdSense forbids paying users to watch/click ads and will ban the account. Compliant options that allow “watch/complete → earn”: <em>AdGate Media, AdGem, BitLabs, CPX Research, Pollfish, Tapjoy, Adscend</em>. They pay you per verified completion and confirm it with a server postback — so rewards are always genuine and profitable.
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Provider name</label>
                    <input wire:model="ad_provider" type="text" placeholder="e.g. AdGate" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Just a label shown to you — pick any of the networks above.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Daily earn cap per user (credits)</label>
                    <input wire:model="ad_daily_cap" type="number" min="0" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Protects the economy from a bad feed. 0 = no cap.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Offerwall URL</label>
                    <input wire:model="ad_offerwall_url" type="url" placeholder="https://wall.adgatemedia.com/…" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">In your network dashboard, create a wall/placement and copy its URL. We append <code class="font-mono">?user=…&amp;sig=…</code> so the network can attribute the reward to the right person.</p>
                    @error('ad_offerwall_url') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-slate-200 p-4 text-xs dark:border-[#2D4060]">
                <p class="font-semibold text-slate-700 dark:text-slate-200">Two credentials to finish setup:</p>
                <ol class="mt-2 list-decimal space-y-1.5 pl-4 text-slate-500 dark:text-slate-400">
                    <li><strong>Postback secret</strong> — set <code class="font-mono">OFFERWALL_POSTBACK_SECRET</code> in your environment. In the network dashboard this is the “security/secret key” used to sign postbacks.
                        Status: @if ($postbackConfigured) <span class="inline-flex items-center gap-1 font-semibold text-green-600 dark:text-green-400"><x-icon name="check" class="h-3.5 w-3.5" /> set</span> @else <span class="font-semibold text-amber-600 dark:text-amber-400">not set</span> @endif
                    </li>
                    <li><strong>Postback URL</strong> — paste this into your network’s “server postback / callback URL” field:<br>
                        <code class="mt-1 block break-all rounded bg-slate-100 px-2 py-1 font-mono text-[11px] text-slate-700 dark:bg-[#243352] dark:text-slate-200">{{ $postbackUrl }}?user={USER_ID}&amp;amount={REWARD}&amp;txn={TRANSACTION_ID}&amp;signature={SIGNATURE}</code>
                        <span class="mt-1 block">where <code class="font-mono">signature = HMAC_SHA256("{USER_ID}:{REWARD}:{TRANSACTION_ID}", secret)</code>. Most networks let you build this from their macros.</span>
                    </li>
                </ol>
            </div>
        </section>

        <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Save settings</span>
            <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
        </button>
    </form>
</div>
