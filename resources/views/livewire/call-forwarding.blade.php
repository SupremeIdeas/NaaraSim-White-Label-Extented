{{-- Call forwarding on the skin system (S3 Batch 6). --}}
<div>
<x-nx.page class="ns-pg">
    <h1 class="ns-h1" style="margin-top:6px">Call forwarding</h1>
    <p class="ns-sub">{{ \App\Support\BrandSettings::rebrand('Send calls to your permanent NaaraSim number straight to your real phone, anywhere in the world. Give out one number, answer it on the phone in your pocket.') }}</p>

    @if ($numbers->isEmpty())
        <div class="ns-pg__card ns-ring" style="text-align:center;padding:40px 24px">
            <span class="ns-tile" style="width:64px;height:64px;font-size:32px;margin:0 auto"><x-nx.icon name="fwd" /></span>
            <h2 class="ns-pg__h2" style="margin-top:16px;font-size:18px">{{ \App\Support\BrandSettings::rebrand('Forwarding needs a Naara Line') }}</h2>
            <p class="ns-sub" style="margin:6px auto 0;max-width:24rem">Get a permanent voice number, then send its calls to the phone in your pocket, anywhere in the world.</p>
            <a href="{{ route('numbers', ['modal' => 'line']) }}" wire:navigate class="ns-cta ns-cta--pill" style="margin:20px auto 0"><x-nx.icon name="plus" /> {{ \App\Support\BrandSettings::rebrand('Get a Naara Line') }}</a>
        </div>
    @else
        <div class="ns-pg__stack" style="margin-top:16px">
            @foreach ($numbers as $number)
                @php($rule = $rules[$number->id] ?? null)
                <div class="ns-pg__card ns-ring" style="margin-top:0" wire:key="num-{{ $number->id }}">
                    <div class="ns-pg__head" style="flex-wrap:nowrap;align-items:center">
                        <div style="display:flex;align-items:center;gap:12px;min-width:0">
                            <span class="ns-tile" style="width:40px;height:40px;font-size:20px"><x-nx.icon name="phone" /></span>
                            <div style="min-width:0">
                                <b class="ns-pg__h2">{{ $number->phone_number }}</b>
                                @if ($rule && $rule->status === 'active')
                                    <p class="ns-small" style="margin:2px 0 0;color:color-mix(in srgb, rgb(var(--nx-ok)) 70%, rgb(var(--nx-text)))">Forwarding to {{ $rule->forward_to_number }}</p>
                                @else
                                    <p class="ns-small" style="margin:2px 0 0">Not forwarding</p>
                                @endif
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;flex:none">
                            @if ($rule && $rule->status === 'active')
                                <button type="button" wire:click="disable({{ $rule->id }})" wire:confirm="Turn off call forwarding for this number?" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Turn off</button>
                            @endif
                            <button type="button" wire:click="edit({{ $number->id }})" class="ns-cta ns-cta--pill ns-cta--sm">{{ $rule && $rule->status === 'active' ? 'Edit' : 'Set up' }}</button>
                        </div>
                    </div>

                    @if ($numberId === $number->id)
                        <div class="ns-pg__sep" style="display:block">
                            @if ($error)<p class="ns-pg__err" role="alert" style="margin-top:0">{{ $error }}</p>@endif
                            <div class="ns-pg__two" style="margin-top:4px">
                                <div>
                                    <label class="ns-pg__lbl" for="cf-to-{{ $number->id }}">Forward calls to</label>
                                    <input id="cf-to-{{ $number->id }}" type="tel" wire:model="forwardTo" placeholder="+2348012345678" class="ns-input">
                                    @error('forwardTo') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="ns-pg__lbl" for="cf-fb-{{ $number->id }}">No-answer backup (optional)</label>
                                    <input id="cf-fb-{{ $number->id }}" type="tel" wire:model="fallback" placeholder="+2348098765432" class="ns-input">
                                    @error('fallback') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="ns-cta" style="margin-top:16px">
                                <span wire:loading.remove wire:target="save" class="ns-cta__label"><x-nx.icon name="check" /> Turn on forwarding</span>
                                <span wire:loading wire:target="save" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-nx.page>
</div>
