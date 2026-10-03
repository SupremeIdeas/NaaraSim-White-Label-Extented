{{-- Merchant earnings on the skin system (S3 Batch 6): period switch, earned total with a dependency-free bar series, breakdown + top customers. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('merchant.dashboard') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> My Storefront</a>
            <h1 class="ns-h1" style="margin-top:6px">Earnings</h1>
        </div>
        <div class="ns-seg ns-small" role="group" aria-label="Period" style="margin-top:8px">
            @foreach ([30 => '30d', 90 => '90d', 365 => '1y'] as $days => $label)
                <button type="button" wire:click="setPeriod({{ $days }})" class="{{ $period === $days ? 'is-on' : '' }}" aria-pressed="{{ $period === $days ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="ns-pg__card ns-ring">
        <span class="ns-pg__lbl" style="text-transform:uppercase;letter-spacing:.06em">Earned · last {{ $period }} days</span>
        <p class="ns-pg__bignum">${{ number_format($total, 2) }}</p>

        {{-- Time-series (dependency-free CSS bars). --}}
        @php($max = max(0.01, collect($series)->max()))
        <div class="ns-pg__bars" role="img" aria-label="Earnings per day">
            @foreach ($series as $day => $amt)
                <div title="{{ $day }}: ${{ number_format($amt, 2) }}"><i style="height: {{ max(1, (int) round($amt / $max * 100)) }}%"></i></div>
            @endforeach
        </div>
    </div>

    <div class="ns-pg__cols2">
        {{-- Breakdown by product line. --}}
        <div class="ns-pg__card ns-ring" style="margin-top:0">
            <h2 class="ns-pg__h2" style="margin-bottom:12px">By product line</h2>
            @php($sourceMax = max(0.01, $bySource->max() ?? 0))
            @forelse ($bySource as $source => $amt)
                <div style="margin-bottom:12px">
                    <div class="ns-pg__metarow"><span>{{ ucwords(str_replace('_', ' ', $source)) }}</span><span>${{ number_format($amt, 2) }}</span></div>
                    <div class="ns-pg__meter"><i style="width: {{ (int) round($amt / $sourceMax * 100) }}%"></i></div>
                </div>
            @empty
                <p class="ns-sub" style="margin:0">No earnings in this window yet.</p>
            @endforelse
        </div>

        {{-- Top customers. --}}
        <div class="ns-pg__card ns-ring" style="margin-top:0">
            <h2 class="ns-pg__h2" style="margin-bottom:8px">Top customers</h2>
            @forelse ($top as $row)
                <div class="ns-pg__lrow" style="padding-inline:0"><b style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500">{{ $row['name'] }}</b><span class="is-pos" style="color:rgb(var(--nx-text))">${{ number_format($row['amount'], 2) }}</span></div>
            @empty
                <p class="ns-sub" style="margin:0">No customer earnings yet.</p>
            @endforelse
        </div>
    </div>
</x-nx.page>
</div>
