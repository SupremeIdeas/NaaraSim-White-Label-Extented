{{-- Identity verification on the skin system (S3 Batch 6). --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <h1 class="ns-h1" style="margin-top:6px">Verify your identity</h1>
    <p class="ns-sub">A one-time check (ID + selfie) that unlocks cash withdrawals. Your ID number is sent straight to our verification partner. We never store it.</p>

    @if ($verified)
        <div class="ns-pg__callout ns-pg__callout--ok" style="margin-top:20px">
            <x-nx.icon name="check" />
            <div><b>Verified</b><p>You're all set to withdraw earnings.</p></div>
        </div>
    @elseif ($attempt && $attempt->status === 'pending')
        <div class="ns-pg__callout ns-pg__callout--warn" style="margin-top:20px">
            <x-nx.icon name="info" />
            <div><b>Verification in progress</b><p>We're reviewing your details. This usually takes a few minutes. You'll be notified as soon as it's done.</p></div>
        </div>
    @else
        @if ($attempt && $attempt->status === 'rejected')
            <div class="ns-pg__err" role="alert">Your last attempt couldn't be verified{{ $attempt->reason ? ': '.$attempt->reason : '.' }} Please check your details and try again.</div>
        @endif

        <form wire:submit="submit" class="ns-pg__card ns-ring">
            <div class="ns-pg__form">
                <div class="ns-pg__two">
                    <div>
                        <label class="ns-pg__lbl" for="iv-country">Country</label>
                        <select id="iv-country" wire:model="country" class="ns-input">
                            <option value="NG">Nigeria</option><option value="GH">Ghana</option><option value="KE">Kenya</option><option value="ZA">South Africa</option>
                        </select>
                        @error('country') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="ns-pg__lbl" for="iv-type">ID type</label>
                        <select id="iv-type" wire:model="idType" class="ns-input">
                            <option value="BVN">BVN</option><option value="NIN">NIN</option><option value="PASSPORT">Passport</option><option value="DRIVERS_LICENSE">Driver's licence</option>
                        </select>
                        @error('idType') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                    </div>
                </div>
                <label class="ns-pg__lbl" for="iv-num">ID number</label>
                <input id="iv-num" type="text" wire:model="idNumber" autocomplete="off" class="ns-input">
                @error('idNumber') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="ns-cta" style="margin-top:16px">
                <span wire:loading.remove wire:target="submit" class="ns-cta__label"><x-nx.icon name="shield" /> Submit for verification</span>
                <span wire:loading wire:target="submit" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Submitting…</span>
            </button>
        </form>
    @endif
</x-nx.page>
</div>
