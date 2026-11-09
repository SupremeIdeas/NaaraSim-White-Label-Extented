<div>
<x-nx.page class="ns-tp">
    <div class="ns-tp__hero">
        <span class="ns-tile"><x-nx.icon name="receipt" /></span>
        <div><h1 class="ns-h1">My top-ups</h1><p class="ns-sub">Every airtime and data top-up you have sent.</p></div>
        <a href="{{ route('topup') }}" wire:navigate class="ns-tp__history"><x-nx.icon name="signal" /> New top-up</a>
    </div>
    @forelse ($orders as $o)
        @php [$label, $variant] = \App\Livewire\TopupOrders::label($o->status); @endphp
        <a href="{{ route('topup.order', $o->uuid) }}" wire:navigate class="ns-card ns-tp__order" wire:key="o{{ $o->id }}">
            @include('livewire.partials.topup.logo', ['name' => $o->operator_name ?? '?', 'size' => 44, 'country' => $o->country_iso])
            <span class="ns-tp__ob">
                <b>{{ $o->operator_name }} · {{ $o->product_type === 'data' ? $o->product_name : $o->send_currency.' '.number_format((float) $o->send_amount, (float) $o->send_amount == floor((float) $o->send_amount) ? 0 : 2) }}</b>
                <span class="ns-small">{{ $o->phone_e164 }} · {{ $o->created_at->diffForHumans() }}</span>
            </span>
            <span class="ns-tp__oa"><b>${{ number_format((float) $o->price_usd, 2) }}</b><x-nx.pill :variant="$variant">{{ $label }}</x-nx.pill></span>
        </a>
    @empty
        <x-nx.empty icon="signal" title="No top-ups yet" text="Send airtime or data to any phone in seconds." />
    @endforelse
    {{ $orders->links() }}
</x-nx.page>
</div>
