<x-layouts.auth title="Create account — NaaraSim" heading="Join NaaraSim" subheading="One account for eSIM data and phone numbers, worldwide.">
    {{-- Merchant co-branding (ROADMAP §Layer 3.3): shown when signing up via a
         reseller invite. NaaraSim branding is never removed — the merchant sits
         alongside it with "Powered by NaaraSim". --}}
    @isset($inviteMerchant)
        @if ($inviteMerchant)
            @php($accent = $inviteMerchant->brand_color ?: '#0A6E6E')
            <div class="mb-4 flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
                @if ($inviteMerchant->logo_url)
                    <img src="{{ $inviteMerchant->logo_url }}" alt="{{ $inviteMerchant->business_name }}" class="h-11 w-11 rounded-xl object-contain ring-1 ring-black/5 dark:ring-white/10">
                @else
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl text-sm font-bold uppercase text-white" style="background-color: {{ $accent }};">{{ \Illuminate\Support\Str::of($inviteMerchant->business_name)->trim()->substr(0, 2) }}</span>
                @endif
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">You're joining via {{ $inviteMerchant->business_name }}</p>
                    <p class="flex items-center gap-1 text-xs text-slate-400 dark:text-slate-500"><x-icon name="signal" class="h-3 w-3" /> Powered by NaaraSim</p>
                </div>
            </div>
        @endif
    @endisset
    <form method="POST" action="/register"
          class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        @csrf
        @if ($errors->any())
            <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <x-auth.social-buttons />
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Password</label>
            <x-ui.password name="password" required
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Confirm password</label>
            <x-ui.password name="password_confirmation" required
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
        </div>
        <x-turnstile />
        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">
            <x-icon name="badge-check" class="h-5 w-5" /> Create account
        </button>
        <p class="text-center text-sm text-slate-500 dark:text-slate-400">
            Already have an account? <a href="/login" class="font-semibold text-primary hover:underline">Sign in</a>
        </p>
    </form>
</x-layouts.auth>
