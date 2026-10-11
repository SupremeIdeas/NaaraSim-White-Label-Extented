{{-- Profile on the skin system (S3 Batch 6): same fields, photo upload, completeness meter, localisation selectors. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <h1 class="ns-h1" style="margin-top:6px">{{ __('account.profile_title') }}</h1>
    <p class="ns-sub">{{ __('account.profile_subtitle') }}</p>

    {{-- Completeness meter --}}
    @php($pct = $user->profileCompleteness())
    <div class="ns-pg__card ns-ring">
        <div class="ns-pg__metarow"><span>{{ __('account.profile_strength') }}</span><span style="font-weight:800;color:{{ $pct >= 80 ? 'color-mix(in srgb, rgb(var(--nx-ok)) 72%, rgb(var(--nx-text)))' : 'color-mix(in srgb, rgb(var(--nx-teal-ink)) 62%, rgb(var(--nx-text)))' }}">{{ $pct }}%</span></div>
        <div class="ns-pg__meter" style="height:9px;margin-top:10px"><i style="width: {{ $pct }}%;{{ $pct >= 80 ? 'background:rgb(var(--nx-ok))' : '' }}"></i></div>
        <p class="ns-pg__hint">{{ $pct >= 80 ? __('account.profile_strength_high') : __('account.profile_strength_low') }}</p>
    </div>

    {{-- Avatar + identity --}}
    <div class="ns-pg__card ns-ring">
        <div style="display:flex;align-items:center;gap:16px">
            @if ($user->avatar)
                <img src="{{ $user->avatar }}" alt="{{ $user->name }}" style="width:64px;height:64px;border-radius:50%;object-fit:cover;flex:none">
            @else
                <span class="ns-avatar" style="width:64px;height:64px;font-size:20px;text-transform:uppercase;background:rgb(var(--nx-surface-3));color:rgb(var(--nx-text))">{{ \Illuminate\Support\Str::of($user->name)->trim()->substr(0, 2) }}</span>
            @endif
            <div style="min-width:0">
                <label class="ns-pg__lbl" for="pf-photo">{{ __('account.profile_photo') }}</label>
                <input id="pf-photo" type="file" wire:model="avatar" accept="image/*" class="ns-ct__file">
                @error('avatar') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                <div wire:loading wire:target="avatar" class="ns-pg__hint">{{ __('account.uploading') }}</div>
            </div>
        </div>

        <div class="ns-pg__form" style="margin-top:12px">
            <div class="ns-pg__two">
                <div>
                    <label class="ns-pg__lbl" for="pf-name">{{ __('account.display_name') }}</label>
                    <input id="pf-name" type="text" wire:model="name" class="ns-input">
                    @error('name') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="ns-pg__lbl" for="pf-phone">{{ __('account.phone') }}</label>
                    <input id="pf-phone" type="tel" wire:model="phone" placeholder="+2348012345678" class="ns-input">
                    @error('phone') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- WhatsApp Autopilot opt-in (§7). Only surfaced once the operator has WhatsApp live; entirely opt-in, and the user can leave any time. --}}
            @if (\App\Support\ProviderStatus::isActive('whatsapp'))
                <div class="ns-pg__kv" style="display:block;margin-top:8px">
                    <label class="ns-pg__check" style="align-items:flex-start">
                        <input type="checkbox" wire:model="whatsappOptIn" style="margin-top:3px">
                        <span>
                            <span style="display:flex;align-items:center;gap:6px;font-weight:600;color:rgb(var(--nx-text))"><x-nx.icon name="message-circle" /> {{ __('account.whatsapp_updates') }}</span>
                            <span class="ns-pg__hint" style="display:block">{{ __('account.whatsapp_updates_body') }}</span>
                        </span>
                    </label>
                    <div style="margin-top:12px" x-data x-show="$wire.whatsappOptIn" x-cloak>
                        <label class="ns-pg__lbl" for="pf-wa">{{ __('account.whatsapp_number') }} <span style="font-weight:400">{{ __('account.optional_defaults_to_phone') }}</span></label>
                        <input id="pf-wa" type="tel" wire:model="whatsappNumber" placeholder="+2348012345678" class="ns-input">
                        @error('whatsappNumber') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                    </div>
                </div>
            @endif

            <label class="ns-pg__lbl" for="pf-bio" style="margin-top:8px">{{ __('account.bio') }} <span style="font-weight:400">{{ __('account.optional') }}</span></label>
            <textarea id="pf-bio" wire:model="bio" rows="3" maxlength="400" placeholder="{{ __('account.bio_placeholder') }}" class="ns-input"></textarea>
            @error('bio') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
        </div>
    </div>

    {{-- Location + preferences --}}
    <div class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:6px">{{ __('account.location_preferences') }}</h2>
        <div class="ns-pg__form">
            <div class="ns-pg__two">
                <div><label class="ns-pg__lbl" for="pf-city">{{ __('account.city') }}</label><input id="pf-city" type="text" wire:model="city" class="ns-input"></div>
                <div>
                    <label class="ns-pg__lbl" for="pf-cc">{{ __('account.country_code') }} <span style="font-weight:400">{{ __('account.country_code_hint') }}</span></label>
                    <input id="pf-cc" type="text" wire:model="countryCode" maxlength="2" class="ns-input" style="text-transform:uppercase">
                    @error('countryCode') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                </div>
                <div><label class="ns-pg__lbl" for="pf-addr">{{ __('account.address') }}</label><input id="pf-addr" type="text" wire:model="addressLine" class="ns-input"></div>
                <div><label class="ns-pg__lbl" for="pf-zip">{{ __('account.postal_code') }}</label><input id="pf-zip" type="text" wire:model="postalCode" class="ns-input"></div>
                <div>
                    <label class="ns-pg__lbl" for="pf-dob">{{ __('account.date_of_birth') }}</label>
                    <input id="pf-dob" type="date" wire:model="dateOfBirth" class="ns-input">
                    @error('dateOfBirth') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="ns-pg__lbl" for="pf-cur">{{ __('account.preferred_currency') }}</label>
                    <select id="pf-cur" wire:model="displayCurrency" class="ns-input">
                        @foreach ($currencyOptions as $code => $meta)<option value="{{ $code }}">{{ $meta[1] }} ({{ $meta[0] }} {{ $code }})</option>@endforeach
                    </select>
                </div>
                <div>
                    {{-- Localization Phase A: the switcher lists the full language roadmap honestly; only real, reviewed translations are selectable. --}}
                    <label class="ns-pg__lbl" for="pf-lang">{{ __('account.language') }}</label>
                    <select id="pf-lang" wire:model="language" class="ns-input">
                        @foreach ($languageOptions as $code => $meta)
                            <option value="{{ $code }}" @disabled(! $meta['available'])>{{ $meta['available'] ? $meta['native'] : __('account.language_coming_soon', ['name' => $meta['native']]) }}</option>
                        @endforeach
                    </select>
                    @error('language') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>
    </div>

    <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,avatar" class="ns-cta" style="margin-top:20px">
        <span class="ns-cta__label"><x-nx.icon name="check" /> {{ __('account.save_profile') }}</span>
    </button>
</x-nx.page>
</div>
