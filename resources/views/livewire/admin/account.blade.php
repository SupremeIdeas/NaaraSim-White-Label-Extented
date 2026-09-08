<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">My account</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Manage your own name, email, password and recovery. Site-wide protection lives on <a href="{{ route('admin.security') }}" wire:navigate class="text-primary hover:underline">Security</a>.</p>
    </div>

    @if ($flash)
        <div class="flex items-center gap-2 rounded-lg px-4 py-3 text-sm {{ $flashType === 'error' ? 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' : 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300' }}">
            <x-icon :name="$flashType === 'error' ? 'x' : 'badge-check'" class="h-4 w-4" /> {{ $flash }}
        </div>
    @endif

    {{-- Profile --}}
    <form wire:submit="updateProfile" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Profile</h2>
        <div class="flex items-center gap-4">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 dark:bg-[#243352]">
                @if ($avatar && $avatar->isPreviewable())
                    <img src="{{ $avatar->temporaryUrl() }}" class="h-full w-full object-cover">
                @elseif ($user->avatar)
                    <img src="{{ $user->avatar }}" class="h-full w-full object-cover">
                @else
                    <x-icon name="id-card" class="h-6 w-6 text-slate-400" />
                @endif
            </span>
            <div class="flex-1">
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Avatar</label>
                <input type="file" wire:model="avatar" accept="image/png,image/jpeg,image/webp"
                       class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary">
                @error('avatar') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Name</label>
            <input type="text" wire:model="name" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Save profile</button>
        </div>
    </form>

    {{-- Email --}}
    <form wire:submit="updateEmail" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Email</h2>
        <p class="text-xs text-slate-400">Changing your email requires your password and a re-verification of the new address.</p>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Email address</label>
            <input type="email" wire:model="new_email" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('new_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Current password</label>
            <input type="password" wire:model="email_password" autocomplete="current-password" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('email_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Change email</button>
        </div>
    </form>

    {{-- Password --}}
    <form wire:submit="updatePassword" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Password</h2>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Current password</label>
            <input type="password" wire:model="current_password" autocomplete="current-password" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">New password</label>
                <input type="password" wire:model="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Confirm new password</label>
                <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Update password</button>
        </div>
    </form>

    {{-- Security questions --}}
    <form wire:submit="saveSecurityQuestions" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Recovery questions</h2>
            @if ($questionsConfigured)
                <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300">Configured</span>
            @endif
        </div>
        <p class="text-xs text-slate-400">Set three questions to recover your account if email reset isn’t available. Answers are hashed — we can never read them.</p>
        @foreach ($questions as $i => $q)
            <div class="grid gap-2 sm:grid-cols-2">
                <select wire:model="questions.{{ $i }}.question" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @foreach ($presets as $preset)
                        <option value="{{ $preset }}">{{ $preset }}</option>
                    @endforeach
                </select>
                <input type="text" wire:model="questions.{{ $i }}.answer" placeholder="Your answer" autocomplete="off"
                       class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error("questions.$i.answer") <p class="text-xs text-red-600 sm:col-span-2">{{ $message }}</p> @enderror
            </div>
        @endforeach
        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Save recovery questions</button>
        </div>
    </form>

    <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-slate-800 dark:text-slate-100">Two-factor authentication</h2>
                <p class="text-xs text-slate-400">Manage your authenticator app and recovery codes.</p>
            </div>
            <a href="{{ route('admin.security') }}" wire:navigate class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">Manage 2FA</a>
        </div>
    </div>
</div>
