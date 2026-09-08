<x-layouts.customer title="Your gift cards">
    <div class="mx-auto max-w-2xl">
        <div class="mb-5 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Your gift cards</h1>
            <a href="{{ route('gift-cards') }}" wire:navigate class="rounded-full bg-primary/10 px-4 py-2 text-sm font-semibold text-primary dark:text-teal-300">Buy more</a>
        </div>

        @forelse ($orders as $order)
            @php $tone = match($order->status){ 'delivered' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'failed','refunded' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300', default => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' }; @endphp
            <a href="{{ route('gift-cards.order', $order) }}" wire:navigate class="mb-2 flex items-center justify-between gap-3 rounded-2xl border border-slate-200/70 bg-white p-4 transition hover:shadow-sm dark:border-white/10 dark:bg-slate-900/60">
                <div class="min-w-0">
                    <p class="truncate font-semibold text-slate-900 dark:text-white">{{ $order->brand_name }}</p>
                    <p class="text-xs text-slate-400">{{ $order->currency }} {{ number_format((float) $order->face_value, 2) }} · {{ $order->created_at->format('M j, Y') }}</p>
                </div>
                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $tone }}">{{ ucfirst($order->status) }}</span>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 py-16 text-center text-sm text-slate-400 dark:border-white/10">No gift cards yet.</div>
        @endforelse
        <div class="mt-4">{{ $orders->links() }}</div>
    </div>
</x-layouts.customer>
