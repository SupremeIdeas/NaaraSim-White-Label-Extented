<x-layouts.marketing :title="'Get the '.$appName.' app'">
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
        <div class="text-center">
            <span class="mb-4 inline-flex rounded-2xl bg-primary/10 p-4 text-primary dark:text-teal-300">
                <x-icon name="download" class="h-8 w-8" />
            </span>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white sm:text-4xl">Get the {{ $appName }} app</h1>
            <p class="mt-3 text-slate-600 dark:text-slate-300">
                Stay connected on the go — your data plans, numbers and wallet in one native app.
                @if ($version)<span class="text-slate-400">Version {{ $version }}</span>@endif
            </p>
        </div>

        <div class="mt-10 grid gap-6 sm:grid-cols-2">
            {{-- Android --}}
            <div class="rounded-3xl border border-slate-200/70 bg-white p-6 dark:border-white/10 dark:bg-slate-900/60">
                <div class="mb-4 flex items-center gap-2 text-slate-800 dark:text-slate-100">
                    <x-icon name="phone" class="h-5 w-5 text-primary dark:text-teal-300" />
                    <h2 class="text-lg font-semibold">Android</h2>
                </div>
                @if ($androidDownloadable)
                    <a href="{{ $apkUrl }}" rel="nofollow"
                        class="flex w-full items-center justify-center gap-2 rounded-full bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:bg-primary-dark">
                        <x-icon name="download" class="h-4 w-4" /> Download APK
                    </a>
                    <p class="mt-2 text-center text-xs text-slate-400">Direct install · signed build</p>
                @endif
                @if ($androidBadge)
                    <a href="{{ $androidStoreUrl }}" target="_blank" rel="noopener nofollow"
                        class="mt-3 flex w-full items-center justify-center gap-2 rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-white/15 dark:text-slate-200 dark:hover:bg-white/5">
                        Get it on Google Play
                    </a>
                @endif
                @unless ($androidDownloadable || $androidBadge)
                    <p class="rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500 dark:bg-white/5 dark:text-slate-400">
                        The Android app is on its way — check back soon.
                    </p>
                @endunless
            </div>

            {{-- iOS — App Store badge only, and only when the listing is live. --}}
            <div class="rounded-3xl border border-slate-200/70 bg-white p-6 dark:border-white/10 dark:bg-slate-900/60">
                <div class="mb-4 flex items-center gap-2 text-slate-800 dark:text-slate-100">
                    <x-icon name="phone" class="h-5 w-5 text-primary dark:text-teal-300" />
                    <h2 class="text-lg font-semibold">iPhone &amp; iPad</h2>
                </div>
                @if ($iosBadge)
                    <a href="{{ $iosStoreUrl }}" target="_blank" rel="noopener nofollow"
                        class="flex w-full items-center justify-center gap-2 rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900">
                        Download on the App Store
                    </a>
                @else
                    <p class="rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500 dark:bg-white/5 dark:text-slate-400">
                        The iOS app will be available on the App Store soon.
                    </p>
                @endif
            </div>
        </div>

        {{-- QR to this page for easy on-device access --}}
        <div class="mt-10 flex flex-col items-center">
            <div class="rounded-2xl border border-slate-200/70 bg-white p-4 dark:border-white/10 dark:bg-white">
                <img src="{{ $qr }}" alt="Scan to open this download page" width="200" height="200" class="h-40 w-40">
            </div>
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Scan to open this page on your phone</p>
        </div>
    </div>
</x-layouts.marketing>
