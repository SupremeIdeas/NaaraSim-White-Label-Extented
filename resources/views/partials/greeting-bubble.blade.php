{{-- The assistant's welcome, styled as a chat message: an avatar next to a
     speech bubble (tail pointing at the avatar). Shared by the inline card and
     the popup. Expects: $gAvatar, $gName, $greeting, $greetingAsk,
     $factOfTheDay, and optionally $onDismiss (an @click expression). --}}
<div class="flex items-start gap-3">
    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10 dark:bg-primary/20"
          aria-hidden="true">
        @if ($gAvatar)
            <img src="{{ $gAvatar }}" alt="" class="h-full w-full object-cover">
        @else
            <x-icon name="message-circle" class="h-5 w-5 text-primary dark:text-teal-300" />
        @endif
    </span>

    <div class="relative min-w-0 flex-1">
        {{-- Speech-bubble tail toward the avatar. --}}
        <span class="absolute -left-1.5 top-3 h-3 w-3 rotate-45 rounded-[2px] bg-white/80 dark:bg-white/[0.07]" aria-hidden="true"></span>
        <div class="relative rounded-2xl rounded-tl-md border border-slate-200/70 bg-white px-4 py-3 dark:border-white/10 dark:bg-[#101d33]">
            @isset($onDismiss)
                <button type="button" {!! $onDismiss !!} aria-label="Dismiss"
                        class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 dark:hover:bg-white/10">
                    <x-icon name="x" class="h-4 w-4" />
                </button>
            @endisset
            <p class="pr-6 text-[13px] font-semibold uppercase tracking-wide text-primary dark:text-teal-300">{{ $gName }}</p>
            <p class="mt-0.5 text-[15px] font-bold text-slate-900 dark:text-slate-100">{{ $greeting }} <span class="font-normal text-slate-500 dark:text-slate-400">— {{ $greetingAsk }}</span></p>
            <p class="mt-1.5 flex items-start gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                <x-icon name="zap" class="mt-0.5 h-4 w-4 shrink-0 text-accent-dark dark:text-accent" />
                <span><span class="font-semibold text-slate-700 dark:text-slate-200">Did you know?</span> {{ $factOfTheDay }}</span>
            </p>
        </div>
    </div>
</div>
