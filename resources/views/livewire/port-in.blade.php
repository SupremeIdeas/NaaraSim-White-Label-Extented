{{-- Bring your number to Naara (US / Canada port-in) on the skin system (S3 Batch 6). Copy and behaviour unchanged. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <a href="{{ route('numbers.lines') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> My Lines</a>
    <h1 class="ns-h1" style="margin-top:8px">Bring your number to Naara</h1>
    <p class="ns-sub">
        Keep your existing US or Canada number and use it on Naara. First we check with the carrier that your number can actually be moved. Then, if it can, you give us a few details and we handle the transfer. It usually takes
        <strong style="color:rgb(var(--nx-text))">5–15 business days</strong>
        (it's the carriers' timeline, not instant), and nothing is charged until your number is live.
        Keep your current line active until it completes.
    </p>

    {{-- Step 1: the eligibility probe. Always shown; changing the number resets it. --}}
    <div class="ns-pg__card ns-ring">
        <form wire:submit="checkEligibility">
            <label class="ns-pg__lbl" for="pi-number" style="font-size:14px">Which number do you want to bring in?</label>
            <div class="ns-pg__row" style="align-items:stretch">
                <input id="pi-number" type="text" wire:model.live.debounce.500ms="phone_number" placeholder="+1 555 000 1234" class="ns-input is-grow">
                <button type="submit" wire:loading.attr="disabled" wire:target="checkEligibility" class="ns-cta ns-cta--pill" style="height:50px">
                    <span wire:loading.remove wire:target="checkEligibility">Check eligibility</span>
                    <span wire:loading wire:target="checkEligibility">Checking…</span>
                </button>
            </div>
            @error('phone_number') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
        </form>

        {{-- Not eligible: an honest sorry, never a promise we can't keep. --}}
        @if ($eligible === false)
            <div class="ns-pg__callout ns-pg__callout--warn" style="margin-top:16px"><x-nx.icon name="info" /><div><p style="margin:0;color:rgb(var(--nx-text))">{{ $eligibilityMessage ?: 'Sorry — this number can’t be brought in right now.' }}</p></div></div>
        @endif

        {{-- Eligible: confirm, then collect the carrier details. --}}
        @if ($eligible === true)
            <div class="ns-pg__callout ns-pg__callout--ok" style="margin-top:16px"><x-nx.icon name="check" /><div><p style="margin:0;color:rgb(var(--nx-text))">Good news — this number can be brought to Naara. Fill in the details below to start the transfer.</p></div></div>

            <div class="ns-pg__callout" style="margin-top:12px"><x-nx.icon name="info" /><div><p style="margin:0">Enter the account holder name and address <strong style="color:rgb(var(--nx-text))">exactly as they appear on your current carrier account</strong>@if ($pinRequired), plus that account's <strong style="color:rgb(var(--nx-text))">number</strong> and <strong style="color:rgb(var(--nx-text))">transfer PIN</strong>@endif. A mismatch is the most common reason a transfer is rejected.</p></div></div>

            <form wire:submit="submit" class="ns-pg__form" style="margin-top:16px">
                <div class="ns-pg__two">
                    <div>
                        <label class="ns-pg__lbl" for="pi-acct">Current account number @unless ($pinRequired)<span style="font-weight:400">(if your carrier requires one)</span>@endunless</label>
                        <input id="pi-acct" type="text" wire:model="account_number" class="ns-input">
                        @error('account_number') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="ns-pg__lbl" for="pi-pin">Transfer PIN @unless ($pinRequired)<span style="font-weight:400">(if required)</span>@endunless</label>
                        <input id="pi-pin" type="text" wire:model="pin" class="ns-input">
                        @error('pin') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="ns-pg__lbl" for="pi-name">Account holder name</label>
                        <input id="pi-name" type="text" wire:model="billing_name" class="ns-input">
                        @error('billing_name') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="ns-pg__lbl" for="pi-addr">Billing address on the account</label>
                        <input id="pi-addr" type="text" wire:model="billing_address" class="ns-input">
                        @error('billing_address') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                    </div>
                </div>
                <label class="ns-pg__lbl" for="pi-notes">Anything else we should know? <span style="font-weight:400">(optional)</span></label>
                <textarea id="pi-notes" wire:model="notes" rows="2" class="ns-input"></textarea>
                @error('notes') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror

                <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="ns-cta" style="margin-top:14px">
                    <span class="ns-cta__label"><x-nx.icon name="fwd" />
                        <span wire:loading.remove wire:target="submit">Submit port-in request</span>
                        <span wire:loading wire:target="submit">Submitting…</span></span>
                </button>
            </form>
        @endif
    </div>

    {{-- The customer's own requests + live status. --}}
    @if ($requests->isNotEmpty())
        <span class="ns-lbl" style="margin-top:22px">Your port-in requests</span>
        <div class="ns-pg__stack ns-pg__stack--sm">
            @foreach ($requests as $request)
                @php($closed = ! $request->isOpen())
                <div class="ns-pg__item ns-ring" style="cursor:default">
                    <span style="min-width:0">
                        <b>{{ $request->phone_number }}</b>
                        <small>Requested {{ $request->created_at->format('M j, Y') }}</small>
                        @if ($request->status === \App\Models\PortInRequest::STATUS_REJECTED && $request->rejection_reason)
                            <small style="color:rgb(var(--nx-bad))">{{ $request->rejection_reason }}</small>
                        @endif
                    </span>
                    <span class="ns-st {{ $request->status === \App\Models\PortInRequest::STATUS_COMPLETED ? 'ns-st--ok' : ($request->status === \App\Models\PortInRequest::STATUS_REJECTED ? 'ns-st--bad' : 'ns-st--info') }}" style="flex:none">{{ \App\Models\PortInRequest::statusLabel($request->status) }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-nx.page>
</div>
