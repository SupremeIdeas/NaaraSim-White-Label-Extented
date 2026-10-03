{{-- Brand dashboard on the skin system (S3 Batch 6): plan/status header, delivery vs guarantee, profile, handles, videos, plan + cancel. --}}
<div>
<x-nx.page>
    {{-- Dashboard header: plan, status and next billing at a glance. A deliberate dark brand panel in every skin and mode. --}}
    @php($statusTone = match ($brand->listing_status) { 'active' => 'is-ok', 'paused_billing' => 'is-warn', default => '' })
    <div class="ns-pg__hero ns-pg__hero--left">
        <div style="max-width:42rem;margin-inline:auto">
            <span class="ns-pg__kicker">Brand dashboard</span>
            <h1 style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
                {{ $brand->brand_name ?: 'My brand listing' }}
            </h1>
            <div class="ns-pg__herometa">
                <span class="ns-st ns-st--ondark {{ $statusTone }}">{{ ucwords(str_replace('_', ' ', $brand->listing_status)) }}</span>
                <span>{{ $brand->plan?->name ?? 'No plan' }}</span>
                @if ($subscription)<span>· Next billing {{ $subscription->next_billing_at?->format('d M Y') }}</span>@endif
            </div>
        </div>
    </div>

    <div class="ns-pg__sheet">
    <div class="ns-pg ns-pg--mid" style="padding:0">

        @if ($flash)<div class="ns-pg__callout ns-pg__callout--ok" role="status"><x-nx.icon name="check" /><div><p style="margin:0;color:rgb(var(--nx-text))">{{ $flash }}</p></div></div>@endif
        @if ($error)<div class="ns-pg__err" role="alert" style="margin-top:0">{{ $error }}</div>@endif

        @unless ($setupComplete)
            <div class="ns-pg__callout ns-pg__callout--warn" style="margin-top:16px"><x-nx.icon name="info" /><div><b>Finish your setup to go live</b><p>Add a brand name, category, a hero image and at least one handle. Your listing appears in the directory once these are complete.</p></div></div>
        @endunless

        {{-- Delivery vs guarantee (transparency) --}}
        @if ($delivery->isNotEmpty())
            <div class="ns-pg__card ns-ring">
                <h2 class="ns-bh__h"><x-nx.icon name="bars" /> Follows delivered this cycle</h2>
                @foreach ($delivery as $row)
                    @php($pct = $row['guaranteed'] > 0 ? min(100, (int) round($row['actual'] / $row['guaranteed'] * 100)) : 0)
                    <div style="margin-bottom:12px">
                        <div class="ns-pg__metarow"><span>{{ $row['handle']->handle_label }}</span><span>{{ $row['actual'] }} / {{ $row['guaranteed'] }} guaranteed</span></div>
                        <div class="ns-pg__meter"><i style="width: {{ $pct }}%;{{ $pct >= 100 ? 'background:rgb(var(--nx-ok))' : '' }}"></i></div>
                    </div>
                @endforeach
                <p class="ns-pg__hint">Under-delivered handles boost your placement next cycle automatically.</p>
            </div>
        @endif

        {{-- Profile --}}
        <div class="ns-pg__card ns-ring">
            <h2 class="ns-bh__h"><x-nx.icon name="cog" /> Profile</h2>
            <div class="ns-pg__form">
                <input wire:model="profile.brand_name" placeholder="Brand name" aria-label="Brand name" class="ns-input">
                @error('profile.brand_name')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
                <select wire:model="profile.category" aria-label="Category" class="ns-input" style="margin-top:8px">
                    <option value="">Choose a category…</option>
                    @foreach ($categories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                </select>
                @error('profile.category')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
                <textarea wire:model="profile.short_description" rows="2" placeholder="Short description" aria-label="Short description" class="ns-input" style="margin-top:8px"></textarea>
                <div class="ns-pg__row" style="margin-top:10px;align-items:center">
                    <label class="ns-pg__lbl" style="margin:0" for="bm-color">Card colour</label>
                    <input id="bm-color" type="color" wire:model="profile.background_color" style="width:56px;height:38px;border-radius:8px;border:1px solid rgb(var(--nx-line-strong));background:transparent">
                    <label class="ns-pg__lbl" style="margin:0" for="bm-hero">Hero image (1280×720)</label>
                    <input id="bm-hero" type="file" wire:model="heroImage" accept="image/*" class="ns-ct__file">
                </div>
                @error('heroImage')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
                @if ($brand->hero_image_path)<img src="{{ $brand->hero_image_path }}" alt="" class="ns-pg__figure" style="height:112px">@endif
                <button type="button" wire:click="saveProfile" wire:loading.attr="disabled" wire:target="saveProfile" class="ns-cta ns-cta--pill" style="align-self:flex-start;margin-top:12px">Save profile</button>
            </div>
        </div>

        {{-- Handles --}}
        <div class="ns-pg__card ns-ring">
            <h2 class="ns-bh__h" style="margin-bottom:4px"><x-nx.icon name="hash" /> Social handles</h2>
            <p class="ns-pg__hint" style="margin:0 0 12px">{{ $brand->handles->count() }} / {{ $brand->plan?->handles_included ?? 1 }} used</p>
            <div class="ns-pg__stack ns-pg__stack--sm">
                @foreach ($brand->handles as $h)
                    <div class="ns-pg__kv" style="gap:10px">
                        <span class="ns-bh__icon" style="width:32px;height:32px;border-radius:9px"><x-service-icon :slug="$h->platform" class="h-4 w-4" /></span>
                        <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:rgb(var(--nx-text))">{{ $h->handle_label }} <span class="ns-small">· {{ $h->handle_url }}</span></span>
                        <button type="button" wire:click="removeHandle({{ $h->id }})" aria-label="Remove {{ $h->handle_label }}" class="ns-ct__act ns-ct__act--spam" style="width:32px;height:32px"><x-nx.icon name="x" /></button>
                    </div>
                @endforeach
            </div>
            <div x-data="{ p: @entangle('newHandle.platform') }" class="ns-pg__form" style="margin-top:12px">
                <div class="ns-pg__two">
                    <select wire:model="newHandle.platform" x-model="p" aria-label="Platform" class="ns-input">@foreach ($platforms as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach</select>
                    <input wire:model="newHandle.handle_label" placeholder="Label" aria-label="Handle label" class="ns-input">
                </div>
                <input wire:model="newHandle.handle_url" placeholder="Profile URL" aria-label="Profile URL" class="ns-input" style="margin-top:8px">
                @error('newHandle.handle_url')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
                <button type="button" wire:click="addHandle" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" style="align-self:flex-start;margin-top:10px">Add handle</button>
            </div>
        </div>

        {{-- Videos (only if the plan allows) --}}
        @if (($brand->plan?->video_previews_allowed ?? 0) > 0)
            <div class="ns-pg__card ns-ring">
                <h2 class="ns-bh__h" style="margin-bottom:4px"><x-nx.icon name="play" /> Video previews</h2>
                <p class="ns-pg__hint" style="margin:0 0 12px">{{ $brand->videos->count() }} / {{ $brand->plan->video_previews_allowed }} used</p>
                <div class="ns-pg__stack ns-pg__stack--sm">
                    @foreach ($brand->videos as $v)
                        <div class="ns-pg__kv" style="gap:10px">
                            <x-nx.icon name="play" />
                            <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:rgb(var(--nx-text))">{{ $v->platform }} · {{ $v->video_url }}</span>
                            <button type="button" wire:click="removeVideo({{ $v->id }})" aria-label="Remove video" class="ns-ct__act ns-ct__act--spam" style="width:32px;height:32px"><x-nx.icon name="x" /></button>
                        </div>
                    @endforeach
                </div>
                <div class="ns-pg__form" style="margin-top:12px">
                    <div class="ns-pg__two">
                        <select wire:model="newVideo.platform" aria-label="Video platform" class="ns-input"><option value="youtube">YouTube</option><option value="vimeo">Vimeo</option></select>
                        <input wire:model="newVideo.video_url" placeholder="Video URL" aria-label="Video URL" class="ns-input">
                    </div>
                    <button type="button" wire:click="addVideo" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" style="align-self:flex-start;margin-top:10px">Add video</button>
                </div>
            </div>
        @endif

        {{-- Plan + cancel --}}
        <div class="ns-pg__card ns-ring">
            <h2 class="ns-bh__h"><x-nx.icon name="cards" /> Plan</h2>
            <div class="ns-pg__chips">
                @foreach ($plans as $plan)
                    <button type="button" wire:click="changePlan({{ $plan->id }})" class="ns-pg__chip {{ $brand->current_plan_id === $plan->id ? 'is-on' : '' }}" aria-pressed="{{ $brand->current_plan_id === $plan->id ? 'true' : 'false' }}">{{ $plan->name }} · ${{ number_format($plan->price_usd_per_month, 0) }}/mo</button>
                @endforeach
            </div>
            <button type="button" wire:click="cancel" wire:confirm="Cancel your listing? It will be removed from the directory. You can resubscribe later." class="ns-pg__act ns-pg__act--bad" style="margin-top:16px">Cancel listing</button>
        </div>
    </div>
    </div>
</x-nx.page>
</div>
