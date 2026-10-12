{{-- "Continue with Google" — only rendered when the admin has configured the
     Google OAuth keys (Module 23). Inline Google "G" mark (SVG, no emoji). --}}
@if (\App\Support\SocialLogin::googleEnabled())
    <div class="space-y-3">
        <a href="{{ route('social.redirect', 'google') }}"
           class="flex w-full items-center justify-center gap-3 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100 dark:hover:bg-[#2D4060]">
            <img src="{{ asset('images/brand/google/google-g.svg') }}" alt="" width="20" height="20" class="h-5 w-5" aria-hidden="true">
            Continue with Google
        </a>
        <div class="flex items-center gap-3">
            <span class="h-px flex-1 bg-slate-200 dark:bg-[#2D4060]"></span>
            <span class="text-xs text-slate-400">or</span>
            <span class="h-px flex-1 bg-slate-200 dark:bg-[#2D4060]"></span>
        </div>
    </div>
@endif
