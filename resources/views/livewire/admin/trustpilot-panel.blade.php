{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $lb = 'mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
    $err = 'mt-1 text-xs text-[rgb(var(--nx-bad))]';
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Trustpilot</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Show your real Trustpilot reviews with Trustpilot's own widgets. Everything is off until you switch it on, and switching it off removes every script and page. This panel only <b>displays</b> reviews. Review invitations and thank-you rewards are not built: Trustpilot's rules on incentives are not settled, so nothing here asks for, steers or pays for reviews.</p>
    </div>

    <details class="{{ $card }}">
        <summary class="cursor-pointer text-base font-semibold text-[rgb(var(--nx-text))]">Quick setup guide</summary>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-[rgb(var(--nx-text))]">
            <li>Create a free business account at trustpilot.com/business and claim your company page.</li>
            <li>Paste your public profile address (it looks like https://www.trustpilot.com/review/your-domain.com) into Profile address below.</li>
            <li>In Trustpilot, open Integrations, then TrustBox library. Pick a widget (Carousel, Mini, Micro combo or Review list), set it to your domain, and copy two values from the snippet it shows you: <b>data-businessunit-id</b> and <b>data-template-id</b>.</li>
            <li>Paste the Business Unit ID once, then each template ID next to the widget it belongs to.</li>
            <li>Switch on the placements you want, press Save, then open the page and confirm the widget appears. If it is blank, check the browser console for a blocked address and add it to the allowed addresses list at the bottom.</li>
            <li>Only reviews that are really on your Trustpilot page are shown. Nothing here filters, hides or rewards reviews.</li>
        </ol>
    </details>

    <form wire:submit="save" class="space-y-6">
        <div class="{{ $card }} space-y-3">
            <label class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model.live="enabled"> Show Trustpilot reviews on this site</label>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $live ? 'Live: widgets load on the pages ticked below.' : 'Not live yet: it needs the switch on and your Business Unit ID.' }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="{{ $lb }}">Business Unit ID</label><input type="text" wire:model="businessUnitId" class="{{ $in }}" placeholder="from your TrustBox snippet">@error('businessUnitId')<p class="{{ $err }}">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}">Language and region</label><input type="text" wire:model="locale" class="{{ $in }}" placeholder="en-US">@error('locale')<p class="{{ $err }}">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label class="{{ $lb }}">Your Trustpilot profile address</label><input type="url" wire:model="profileUrl" class="{{ $in }}" placeholder="https://www.trustpilot.com/review/yourdomain.com">@error('profileUrl')<p class="{{ $err }}">{{ $message }}</p>@enderror</div>
            </div>
            <label class="flex items-start gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="clickToLoad" class="mt-1"> <span>Ask visitors before loading Trustpilot<small class="block text-xs text-[rgb(var(--nx-text-2))]">This platform has no cookie banner. Trustpilot's widget loads from their servers and may set cookies, so turn this on where visitors must consent first.</small></span></label>
        </div>

        <div class="{{ $card }} space-y-3">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Widget template IDs</h2>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">Create each widget in Trustpilot's TrustBox builder, then paste the template ID from the snippet it gives you. We do not ship guessed IDs. A page with no template ID for its widget type shows a plain link to your profile instead.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($types as $t => $label)
                    <div><label class="{{ $lb }}">{{ $label }}</label><input type="text" wire:model="templates.{{ $t }}" class="{{ $in }}">@error('templates.'.$t)<p class="{{ $err }}">{{ $message }}</p>@enderror</div>
                @endforeach
            </div>
        </div>

        <div class="{{ $card }} space-y-3">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Where to show it</h2>
            @foreach ($places as $k => $p)
                <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="placements.{{ $k }}"> {{ $p['label'] }} <small class="text-[rgb(var(--nx-text-2))]">({{ $types[$p['type']] }} widget)</small></label>
            @endforeach
            @if ($missingTemplates)<p class="text-xs text-[rgb(var(--nx-text-2))]" role="status">Some ticked places have no template ID yet, so they show a profile link for now.</p>@endif
            <label class="flex items-start gap-2 pt-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="defaultTestimonials" class="mt-1"> <span>Keep the platform's own testimonials on the homepage<small class="block text-xs text-[rgb(var(--nx-text-2))]">Independent of Trustpilot. Both can show together; switch either one off whenever you like.</small></span></label>
        </div>

        <div class="{{ $card }} space-y-3">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Reviews page (/reviews)</h2>
            <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="pageEnabled"> Publish a dedicated reviews page</label>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">It shows Trustpilot's review list widget with all star ratings, never a filtered selection. Turn it on once you have enough reviews to make it worthwhile.</p>
            <div class="grid gap-3"><div><label class="{{ $lb }}">Page title</label><input type="text" wire:model="pageTitle" class="{{ $in }}" placeholder="Customer reviews"></div><div><label class="{{ $lb }}">Intro text</label><textarea wire:model="pageIntro" rows="2" class="{{ $in }}"></textarea></div></div>
        </div>

        <div class="{{ $card }} space-y-3">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Allowed Trustpilot addresses (security policy)</h2>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">Added to the site's Content Security Policy only while Trustpilot is on. These are a starting point: if a widget does not appear, open the browser console, read which address the policy blocked, and add it here.</p>
            @foreach ($csp as $dir => $v)
                <div><label class="{{ $lb }}">{{ $dir }}</label><input type="text" wire:model="csp.{{ $dir }}" class="{{ $in }}"></div>
            @endforeach
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving...</span></button>
    </form>
</div>
