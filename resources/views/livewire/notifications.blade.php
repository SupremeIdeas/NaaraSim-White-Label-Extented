{{-- Notifications on the skin system (S3 Batch 3): same filters, mark-read, offer styles (banner hero / dark feature), pagination. --}}
<div>
<x-nx.page class="ns-narrow ns-nt">
    <div class="ns-nt__top">
        <div>
            <h1 class="ns-h1" style="margin-top:6px">Notifications</h1>
            <p class="ns-sub">Everything that concerns your account, in one place.</p>
        </div>
        @if ($unread > 0)
            <button type="button" wire:click="markAllRead" wire:loading.attr="disabled" wire:target="markAllRead" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Mark all read</button>
        @endif
    </div>

    {{-- Filters --}}
    <div class="ns-nt__filters" role="group" aria-label="Filter notifications">
        @foreach (['' => 'All', 'unread' => 'Unread', 'offer' => 'Offers', 'order' => 'Orders', 'wallet' => 'Wallet', 'support' => 'Support', 'security' => 'Security'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')" class="ns-nt__chip {{ $filter === $key ? 'is-on' : '' }}" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="ns-nt__list">
        @forelse ($notifications as $note)
            @php $d = $note->data; $isUnread = is_null($note->read_at); @endphp

            {{-- Tier 5 #11 Phase A1: an 'offer' announcement gets its chosen presentation style on this, the real notification surface. --}}
            @if (($d['category'] ?? null) === 'offer' && ($d['style'] ?? null) === 'banner_hero')
                <div wire:key="n-{{ $note->id }}" class="ns-nt__card ns-nt__card--hero ns-ring {{ $isUnread ? 'is-unread' : '' }}">
                    @if (! empty($d['image_url']))
                        <img src="{{ $d['image_url'] }}" alt="" class="ns-nt__hero">
                    @endif
                    <div class="ns-nt__body">
                        <div class="ns-nt__titlerow">
                            <b class="ns-nt__title ns-nt__title--lg">{{ $d['title'] ?? 'Notification' }}</b>
                            @if ($isUnread)<span class="ns-nt__dot" role="img" aria-label="Unread"></span>@endif
                        </div>
                        @if (! empty($d['body']))<p class="ns-nt__text">{{ $d['body'] }}</p>@endif
                        <div class="ns-nt__meta">
                            @if (! empty($d['action_url']))
                                <button type="button" wire:click="go('{{ $note->id }}')" wire:loading.attr="disabled" class="ns-cta ns-cta--pill ns-cta--sm">{{ $d['action_label'] ?? 'View' }}</button>
                            @endif
                            <span class="ns-small">{{ $note->created_at->diffForHumans() }}</span>
                            @if ($isUnread)<button type="button" wire:click="markAsRead('{{ $note->id }}')" class="ns-nt__link">Mark read</button>@endif
                        </div>
                    </div>
                </div>
            @elseif (($d['category'] ?? null) === 'offer' && ($d['style'] ?? null) === 'dark_feature')
                <div wire:key="n-{{ $note->id }}" class="ns-nt__card ns-nt__card--dark {{ $isUnread ? 'is-unread' : '' }}">
                    <div class="ns-nt__darkrow">
                        @if (! empty($d['feature_image_url']))
                            <img src="{{ $d['feature_image_url'] }}" alt="" class="ns-nt__thumb">
                        @endif
                        <div class="ns-nt__darkmain">
                            <div class="ns-nt__titlerow">
                                <b class="ns-nt__title ns-nt__title--lg">{{ $d['title'] ?? 'Notification' }}</b>
                                @if ($isUnread)<span class="ns-nt__dot" role="img" aria-label="Unread"></span>@endif
                            </div>
                            @if (! empty($d['body']))<p class="ns-nt__text">{{ $d['body'] }}</p>@endif
                            @if (! empty($d['bullets']))
                                <ul class="ns-nt__bullets">
                                    @foreach ($d['bullets'] as $bullet)
                                        <li><x-nx.icon name="check" /> {{ $bullet }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                    <div class="ns-nt__meta">
                        @if (! empty($d['action_url']))
                            <button type="button" wire:click="go('{{ $note->id }}')" wire:loading.attr="disabled" class="ns-cta ns-cta--pill ns-cta--sm">{{ $d['action_label'] ?? 'View' }}</button>
                        @endif
                        @if (! empty($d['secondary_url']))
                            <a href="{{ $d['secondary_url'] }}" wire:navigate class="ns-nt__link">{{ $d['secondary_label'] ?? 'Learn more' }} →</a>
                        @endif
                        <span class="ns-small">{{ $note->created_at->diffForHumans() }}</span>
                        @if ($isUnread)<button type="button" wire:click="markAsRead('{{ $note->id }}')" class="ns-nt__link">Mark read</button>@endif
                    </div>
                </div>
            @else
                <div wire:key="n-{{ $note->id }}" class="ns-nt__card ns-nt__card--row ns-ring {{ $isUnread ? 'is-unread' : '' }}">
                    <span class="ns-tile" style="width:40px;height:40px;font-size:20px"><x-nx.icon :name="$d['icon'] ?? 'bell'" /></span>
                    <div class="ns-nt__main">
                        <b class="ns-nt__title">{{ $d['title'] ?? 'Notification' }}</b>
                        @if (! empty($d['body']))<p class="ns-nt__text">{{ $d['body'] }}</p>@endif
                        <div class="ns-nt__meta">
                            <span class="ns-small">{{ $note->created_at->diffForHumans() }}</span>
                            @if (! empty($d['action_url']))
                                <button type="button" wire:click="go('{{ $note->id }}')" class="ns-nt__link ns-nt__link--strong">{{ $d['action_label'] ?? 'View' }} →</button>
                            @endif
                            @if ($isUnread)<button type="button" wire:click="markAsRead('{{ $note->id }}')" class="ns-nt__link">Mark read</button>@endif
                        </div>
                    </div>
                    @if ($isUnread)<span class="ns-nt__dot" role="img" aria-label="Unread"></span>@endif
                </div>
            @endif
        @empty
            <x-nx.empty title="Nothing here yet." text="New activity on your account shows up here." />
        @endforelse
    </div>

    <div class="ns-nt__pages">{{ $notifications->links() }}</div>
</x-nx.page>
</div>
