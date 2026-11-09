@php
    [$label, $variant] = \App\Livewire\TopupOrders::label($order->status);
    $ev = $order->events->pluck('type')->all();
    $sentAt = $order->submitted_at;
@endphp
<div @unless ($settled) wire:poll.4s @endunless>
<x-nx.page class="ns-tp">
    <div class="ns-tp__hero">
        <span class="ns-tile"><x-nx.icon name="{{ $order->status === 'delivered' ? 'check' : ($label === 'Refunded' ? 'alert-triangle' : 'clock') }}" /></span>
        <div><h1 class="ns-h1">{{ $order->status === 'delivered' ? 'Top-up delivered' : ($label === 'Refunded' ? 'We refunded this top-up' : 'Sending your top-up') }}</h1>
            <p class="ns-sub">@if ($order->status === 'delivered') {{ $order->operator_name }} · {{ $order->phone_e164 }} has been topped up.
                @elseif ($label === 'Refunded') It could not be delivered. ${{ number_format((float) $order->price_usd, 2) }} is back in your wallet.
                @else This usually takes a few seconds. You can leave this page: we will let you know. @endif</p></div>
        <a href="{{ route('topup.orders') }}" wire:navigate class="ns-tp__history"><x-nx.icon name="receipt" /> All top-ups</a>
    </div>
    <section class="ns-card ns-tp__step">
        <div class="ns-tp__recv">@include('livewire.partials.topup.logo', ['name' => $order->operator_name ?? '?', 'size' => 56, 'country' => $order->country_iso])
            <div><b>{{ $order->operator_name }}</b><br><span class="ns-small">{{ $order->product_type === 'data' ? $order->product_name : $order->send_currency.' '.number_format((float) $order->send_amount, 2) }}</span></div>
            <x-nx.pill :variant="$variant">{{ $label }}</x-nx.pill></div>
        <dl class="ns-tp__sum">
            <div><dt>Sent to</dt><dd>{{ $order->phone_e164 }}</dd></div>
            <div><dt>You paid</dt><dd>${{ number_format((float) $order->price_usd, 2) }}@if ((float) $order->credit_usd > 0) <span class="ns-small">(after a ${{ number_format((float) $order->credit_usd, 2) }} Credit discount)</span>@endif</dd></div>
            <div><dt>Receipt</dt><dd>{{ strtoupper(substr($order->uuid, 0, 8)) }}</dd></div>
            <div><dt>Started</dt><dd>{{ $order->created_at->format('j M Y, H:i') }}</dd></div>
            @if ($order->delivered_at)<div><dt>Delivered</dt><dd>{{ $order->delivered_at->format('j M Y, H:i') }}</dd></div>@endif
            @if (! empty($order->meta['receipt']))<div><dt>Operator receipt</dt><dd>{{ $order->meta['receipt'] }}</dd></div>@endif
        </dl>
        <ol class="ns-tp__tl">
            <li class="is-done"><x-nx.icon name="check" /> Paid from your wallet</li>
            <li class="{{ $sentAt ? 'is-done' : '' }}"><x-nx.icon name="{{ $sentAt ? 'check' : 'clock' }}" /> Sent to the network</li>
            <li class="{{ $order->status === 'delivered' ? 'is-done' : '' }}"><x-nx.icon name="{{ $order->status === 'delivered' ? 'check' : 'clock' }}" /> {{ $label === 'Refunded' ? 'Could not be delivered: refunded' : 'Delivered' }}</li>
        </ol>
        @if ($order->escalated && ! $settled)<p class="ns-small ns-tp__lock"><x-nx.icon name="info" /> This one is taking longer than usual. Our team is checking it. You will not be charged for a top-up that does not arrive.</p>@endif
        <a href="{{ route('topup') }}" wire:navigate class="ns-cta ns-cta--pill" style="margin-top:14px"><x-nx.icon name="signal" /> Top up another number</a>
    </section>
</x-nx.page>
</div>
