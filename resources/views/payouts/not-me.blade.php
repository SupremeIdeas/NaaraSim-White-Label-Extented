<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('payouts.not_me.title') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
            </div>
            <h1 class="text-lg font-bold">{{ __('payouts.not_me.title') }}</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('payouts.not_me.body') }}</p>
            @if ($cancelled > 0)
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ trans_choice('payouts.not_me.cancelled', $cancelled, ['count' => $cancelled]) }}</p>
            @endif
            <a href="{{ url('/forgot-password') }}" class="mt-5 inline-flex rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white">{{ __('payouts.not_me.reset') }}</a>
        </div>
    </main>
</body>
</html>
