<x-layouts.customer title="Staff earnings">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-white">Your staff earnings</h1>
        <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
            Your monthly profit-share, paid automatically to your verified payout account.
        </p>
        <livewire:payout-dashboard earner-type="staff" />
    </div>
</x-layouts.customer>
