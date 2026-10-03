<div>
    @if ($needed)
        <div class="ns-note ns-note--warn" style="display:block" role="group" aria-labelledby="stepup-title">
            <div style="display:flex;gap:12px;align-items:flex-start">
                <x-nx.icon name="shield" />
                <div style="min-width:0;flex:1">
                    <b id="stepup-title" style="display:block;color:rgb(var(--nx-text));font-weight:600">{{ __('payouts.step_up.title') }}</b>
                    <p style="margin:4px 0 0">{{ __('payouts.step_up.body') }}</p>

                    @if ($error)<p style="margin:8px 0 0;color:rgb(var(--nx-bad))" role="alert">{{ $error }}</p>@endif
                    @if ($message)<p style="margin:8px 0 0;color:rgb(var(--nx-ok))" role="status">{{ $message }}</p>@endif

                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:12px">
                        @if (! $authenticator)
                            <button type="button" class="ns-btn" style="margin:0;height:40px" wire:click="send" wire:loading.attr="disabled" wire:target="send">{{ __('payouts.step_up.send') }}</button>
                        @endif
                        <label class="ns-search" style="margin:0;height:40px;width:11rem">
                            <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="8" wire:model="code" wire:keydown.enter="verify"
                                   placeholder="{{ __('payouts.step_up.enter') }}" aria-label="{{ __('payouts.step_up.enter') }}">
                        </label>
                        <button type="button" class="ns-btn ns-btn--solid" style="margin:0;height:40px" wire:click="verify" wire:loading.attr="disabled" wire:target="verify">{{ __('payouts.step_up.verify') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($message)
        <x-nx.note icon="check" role="status">{{ $message }}</x-nx.note>
    @endif
</div>
