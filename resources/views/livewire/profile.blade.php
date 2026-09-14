<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ __('account.profile_title') }}</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">{{ __('account.profile_subtitle') }}</p>

    {{-- Completeness meter --}}
    @php($pct = $user->profileCompleteness())
    <div class="mb-6 rounded-2xl border border-slate-200 nx-glass-tile p-5 dark:border-[var(--brand-card-border-dark)]">
        <div class="mb-2 flex items-center justify-between text-sm">
            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ __('account.profile_strength') }}</span>
            <span class="font-bold {{ $pct >= 80 ? 'text-green-600 dark:text-green-400' : 'text-primary dark:text-teal-300' }}">{{ $pct }}%</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-[#243352]">
            <div class="h-full rounded-full transition-all {{ $pct >= 80 ? 'bg-green-500' : 'bg-primary' }}" style="width: {{ $pct }}%"></div>
        </div>
        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">{{ $pct >= 80 ? __('account.profile_strength_high') : __('account.profile_strength_low') }}</p>
    </div>

    <div class="space-y-6">
        {{-- Avatar + identity --}}
        <div class="rounded-2xl border border-slate-200 nx-glass-tile p-6 dark:border-[var(--brand-card-border-dark)]">
            <div class="flex items-center gap-4">
                @if ($user->avatar)
                    <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-full object-cover ring-1 ring-black/5 dark:ring-white/10">
                @else
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-lg font-bold uppercase text-primary dark:bg-primary/20 dark:text-teal-300">{{ \Illuminate\Support\Str::of($user->name)->trim()->substr(0, 2) }}</span>
                @endif
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('account.profile_photo') }}</label>
                    <input type="file" wire:model="avatar" accept="image/*"
                           class="block text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-300 dark:file:bg-primary/20 dark:file:text-teal-300">
                    @error('avatar') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="avatar" class="mt-1 text-xs text-slate-400">{{ __('account.uploading') }}</div>
                </div>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('account.display_name') }}</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('account.phone') }}</label>
                    <input type="tel" wire:model="phone" placeholder="+2348012345678" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                    @error('phone') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- WhatsApp Autopilot opt-in (§7). Only surfaced once the operator
                 has WhatsApp live; entirely opt-in, and the user can leave any
                 time (here, or by replying STOP on WhatsApp). --}}
            @if (\App\Support\ProviderStatus::isActive('whatsapp'))
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-[var(--brand-card-border-dark)] dark:bg-[#141F33]">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" wire:model="whatsappOptIn" class="mt-0.5 rounded border-slate-300 text-primary focus:ring-primary dark:border-[var(--brand-card-border-dark)]">
                        <span class="text-sm">
                            <span class="flex items-center gap-1.5 font-medium text-slate-800 dark:text-slate-100">
                                <x-icon name="message-circle" class="h-4 w-4 text-primary" /> {{ __('account.whatsapp_updates') }}
                            </span>
                            <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ __('account.whatsapp_updates_body') }}</span>
                        </span>
                    </label>
                    <div class="mt-3" x-data x-show="$wire.whatsappOptIn" x-cloak>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('account.whatsapp_number') }} <span class="font-normal text-slate-400">{{ __('account.optional_defaults_to_phone') }}</span></label>
                        <input type="tel" wire:model="whatsappNumber" placeholder="+2348012345678" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                        @error('whatsappNumber') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>
            @endif
            <div class="mt-4">
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('account.bio') }} <span class="font-normal text-slate-400">{{ __('account.optional') }}</span></label>
                <textarea wire:model="bio" rows="3" maxlength="400" placeholder="{{ __('account.bio_placeholder') }}" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100"></textarea>
                @error('bio') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Location + preferences --}}
        <div class="rounded-2xl border border-slate-200 nx-glass-tile p-6 dark:border-[var(--brand-card-border-dark)]">
            <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('account.location_preferences') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('account.city') }}</label>
                    <input type="text" wire:model="city" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('account.country_code') }} <span class="font-normal text-slate-400">{{ __('account.country_code_hint') }}</span></label>
                    <input type="text" wire:model="countryCode" maxlength="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm uppercase text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                    @error('countryCode') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('account.address') }}</label>
                    <input type="text" wire:model="addressLine" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('account.postal_code') }}</label>
                    <input type="text" wire:model="postalCode" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('account.date_of_birth') }}</label>
                    <input type="date" wire:model="dateOfBirth" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                    @error('dateOfBirth') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('account.preferred_currency') }}</label>
                    <select wire:model="displayCurrency" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                        @foreach ($currencyOptions as $code => $meta)
                            <option value="{{ $code }}">{{ $meta[1] }} ({{ $meta[0] }} {{ $code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    {{-- Localization Phase A: the switcher lists the full language
                         roadmap honestly — only real, reviewed translations are
                         selectable; the rest show as "coming soon" until Phase C/D
                         ships them (Arabic also needs RTL verification first). --}}
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('account.language') }}</label>
                    <select wire:model="language" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                        @foreach ($languageOptions as $code => $meta)
                            <option value="{{ $code }}" @disabled(! $meta['available'])>
                                {{ $meta['available'] ? $meta['native'] : __('account.language_coming_soon', ['name' => $meta['native']]) }}
                            </option>
                        @endforeach
                    </select>
                    @error('language') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,avatar"
                class="flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
            <x-icon name="check" class="h-4 w-4" /> {{ __('account.save_profile') }}
        </button>
    </div>
</div>
