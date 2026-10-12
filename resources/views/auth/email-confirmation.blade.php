@php
    $copy = [
        'done' => ['Email confirmed', "You're all set. Sign in to continue.", 'success'],
        'already' => ['Already confirmed', 'This email address is already confirmed. You can sign in.', 'info'],
        'expired' => ['This link has expired', 'Confirmation links work for a limited time, or this one was changed. Ask for a fresh one.', 'info'],
        'invalid' => ['This link is not valid', 'We could not match this link to an account. Sign in and request a new confirmation email.', 'info'],
    ][$state];
@endphp
<x-layouts.auth :title="$copy[0].' | '.config('app.name')" :heading="$copy[0]" :subheading="$copy[1]">
    <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full {{ $copy[2] === 'success' ? 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
            <x-icon :name="$copy[2] === 'success' ? 'badge-check' : 'mail'" class="h-7 w-7" />
        </div>
        @if (in_array($state, ['expired', 'invalid'], true) && $canResend)
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark"><x-icon name="send" class="h-5 w-5" /> Send me a new link</button>
            </form>
        @elseif (in_array($state, ['expired', 'invalid'], true))
            <a href="{{ route('login') }}" class="flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">Sign in to get a new link</a>
        @else
            <a href="{{ route('login') }}" class="flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">Continue to sign in</a>
        @endif
    </div>
</x-layouts.auth>
