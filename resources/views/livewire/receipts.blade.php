{{-- Receipts on the skin system (S3 Batch 3): every purchase as a list row, same "Email me" resend and pagination. --}}
<x-nx.page class="ns-narrow">
    <h1 class="ns-h1" style="margin-top:6px">Receipts</h1>
    <p class="ns-sub">Every purchase, for your records. Save, re-view, or resend any receipt to your email.</p>

    @php($fmt = app(\App\Services\Pricing\CurrencyService::class))
    @php($local = \App\Support\LocaleCurrency::resolve(auth()->user()))

    @forelse ($receipts as $r)
        <div wire:key="rcpt-{{ $r->id }}" class="ns-row ns-ring" style="height:auto;min-height:64px;padding:12px 14px">
            <span class="ns-tile" style="width:42px;height:42px;font-size:21px"><x-nx.icon name="file-text" /></span>
            <span class="ns-row__label" style="display:block;min-width:0">
                <b style="display:block;font-size:16px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $r->description ?: 'Purchase' }}</b>
                <small class="ns-small" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $r->created_at->format('d M Y, H:i') }} · Ref {{ $r->reference ?: 'TX-'.$r->id }}</small>
            </span>
            <span class="ns-row__price" style="text-align:end">
                @if (strtoupper($r->currency) === 'USD')${{ number_format(abs($r->amount), 2) }}@else{{ strtoupper($r->currency) }} {{ number_format(abs($r->amount), 2) }}@endif
                @if (strtoupper($r->currency) === 'USD' && $local !== 'USD')<small class="ns-small" style="display:block;font-weight:400">{{ $fmt->format(abs($r->amount), $local) }}</small>@endif
                <button type="button" class="ns-link" wire:click="resend({{ $r->id }})" wire:loading.attr="disabled" wire:target="resend({{ $r->id }})">Email me</button>
            </span>
        </div>
    @empty
        <x-nx.empty text="No purchases yet — your receipts will appear here." style="margin-top:16px" />
    @endforelse

    <div style="margin-top:18px">{{ $receipts->links() }}</div>
</x-nx.page>
