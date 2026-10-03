<div x-data="{ create: @entangle('showCreate') }">
<x-nx.page class="ns-pg ns-pg--wide">
    {{-- Header --}}
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('merchant.clients') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> Clients</a>
            <h1 class="ns-h1" style="margin-top:6px">Invoices</h1>
        </div>
        <button type="button" wire:click="openCreate" @click="create = true" class="ns-cta ns-cta--pill" style="margin-top:8px"><x-nx.icon name="plus" /> Create invoice</button>
    </div>

    {{-- Stat row --}}
    <div class="ns-pg__stats">
        <div class="ns-pg__stat ns-ring"><small>Overdue</small><b class="is-bad">${{ number_format($overdueTotal, 2) }}</b></div>
        <div class="ns-pg__stat ns-ring"><small>Due within 30 days</small><b>${{ number_format($dueSoonTotal, 2) }}</b></div>
        <div class="ns-pg__stat ns-ring"><small>Avg. time to get paid</small><b>{{ $avgDaysToPay !== null ? $avgDaysToPay.' days' : '—' }}</b></div>
        <div class="ns-pg__stat ns-pg__stat--hi">
            <small>Available for payout</small>
            <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:8px">
                <b>${{ number_format($payoutAvailable, 2) }}</b>
                <a href="{{ route('merchant.dashboard') }}" wire:navigate class="ns-cta ns-cta--pill ns-cta--sm" style="height:30px;padding:0 12px;font-size:12.5px">Pay out</a>
            </div>
        </div>
    </div>

    {{-- Filters + search --}}
    <div class="ns-pg__filters">
        @foreach (['all' => 'All', 'draft' => 'Draft', 'unpaid' => 'Unpaid', 'paid' => 'Paid'] as $key => $label)
            <button type="button" wire:click="$set('statusFilter', '{{ $key }}')" class="ns-nt__chip {{ $statusFilter === $key ? 'is-on' : '' }}" aria-pressed="{{ $statusFilter === $key ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
        <label class="ns-search"><x-nx.icon name="search" /><input type="text" wire:model.live.debounce.300ms="search" placeholder="Search…" aria-label="Search invoices"></label>
    </div>

    <div class="ns-pg__split">
        {{-- List --}}
        <div class="ns-pg__stack ns-pg__stack--sm">
            @forelse ($invoices as $invoice)
                @php
                    $badge = match (true) {
                        $invoice->status === \App\Models\MerchantInvoice::PAID => ['label' => 'Paid', 'class' => 'ns-st--ok'],
                        $invoice->status === \App\Models\MerchantInvoice::VOID => ['label' => 'Void', 'class' => ''],
                        $invoice->status === \App\Models\MerchantInvoice::DRAFT => ['label' => 'Draft', 'class' => ''],
                        $invoice->isOverdue() => ['label' => 'Overdue', 'class' => 'ns-st--bad'],
                        $invoice->viewed_at !== null => ['label' => 'Viewed', 'class' => 'ns-st--info'],
                        default => ['label' => 'Unsent', 'class' => 'ns-st--warn'],
                    };
                @endphp
                <button type="button" wire:click="select({{ $invoice->id }})" wire:key="inv-{{ $invoice->id }}" class="ns-pg__item ns-ring {{ $selected?->id === $invoice->id ? 'is-on' : '' }}">
                    <span style="min-width:0">
                        <b>{{ $invoice->client->name }}</b>
                        <small>{{ $invoice->reference }} @if ($invoice->due_at) · due {{ $invoice->due_at->format('M j') }} @endif</small>
                    </span>
                    <span style="text-align:end;flex:none">
                        <span class="ns-pg__amount" style="display:block">${{ number_format((float) $invoice->amount, 2) }}</span>
                        <span class="ns-st {{ $badge['class'] }}" style="margin-top:4px">{{ $badge['label'] }}</span>
                    </span>
                </button>
            @empty
                <x-nx.empty text="No invoices yet. Create one for a client." />
            @endforelse
            <div style="margin-top:8px">{{ $invoices->links() }}</div>
        </div>

        {{-- Detail panel --}}
        <div class="ns-pg__card ns-ring" style="margin-top:0">
            @if ($selected)
                @php
                    $badge = match (true) {
                        $selected->status === \App\Models\MerchantInvoice::PAID => ['label' => 'Paid', 'class' => 'ns-st--ok'],
                        $selected->status === \App\Models\MerchantInvoice::VOID => ['label' => 'Void', 'class' => ''],
                        $selected->status === \App\Models\MerchantInvoice::DRAFT => ['label' => 'Draft', 'class' => ''],
                        $selected->isOverdue() => ['label' => 'Overdue', 'class' => 'ns-st--bad'],
                        $selected->viewed_at !== null => ['label' => 'Viewed', 'class' => 'ns-st--info'],
                        default => ['label' => 'Unsent', 'class' => 'ns-st--warn'],
                    };
                @endphp
                <div class="ns-pg__head" style="flex-wrap:nowrap;align-items:flex-start">
                    <div>
                        <small class="ns-small">{{ $selected->reference }}</small>
                        <h2 class="ns-pg__h2" style="font-size:18px">{{ $selected->client->name }}</h2>
                    </div>
                    <span class="ns-st {{ $badge['class'] }}" style="flex:none">{{ $badge['label'] }}</span>
                </div>

                <div class="ns-pg__kv" style="display:block;margin-top:16px;padding:16px">
                    <span style="display:block">{{ $selected->description }}</span>
                    <p class="ns-pg__big">${{ number_format((float) $selected->amount, 2) }}</p>
                </div>

                <dl class="ns-pg__dl">
                    @if ($selected->due_at)<div><dt>Due</dt><dd>{{ $selected->due_at->format('M j, Y') }}</dd></div>@endif
                    @if ($selected->sent_at)<div><dt>Sent</dt><dd>{{ $selected->sent_at->format('M j, Y') }}</dd></div>@endif
                    @if ($selected->paid_at)<div><dt>Paid</dt><dd style="color:rgb(var(--nx-ok))">{{ $selected->paid_at->format('M j, Y') }}</dd></div>@endif
                </dl>

                @php($publicUrl = route('invoice.public', $selected->public_token))
                <div class="ns-pg__btns">
                    @if ($selected->status === \App\Models\MerchantInvoice::DRAFT)
                        <button type="button" wire:click="send({{ $selected->id }})" wire:loading.attr="disabled" wire:target="send({{ $selected->id }})" class="ns-cta ns-cta--pill ns-cta--sm"><x-nx.icon name="send" /> Send</button>
                        <button type="button" wire:click="void({{ $selected->id }})" wire:confirm="Void this draft invoice?" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Void</button>
                    @elseif ($selected->status === \App\Models\MerchantInvoice::SENT)
                        @if ($selected->client->whatsapp)
                            <a href="{{ $selected->client->whatsappLink("Hi {$selected->client->name}, here's your invoice for {$selected->description} — \${$selected->amount}. View it here: {$publicUrl}") }}" target="_blank" rel="noopener" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm"><x-nx.icon name="message-circle" /> WhatsApp</a>
                        @endif
                        <button type="button" wire:click="markPaid({{ $selected->id }})" wire:confirm="Mark this invoice as paid?" wire:loading.attr="disabled" wire:target="markPaid({{ $selected->id }})" class="ns-cta ns-cta--pill ns-cta--sm"><x-nx.icon name="check" /> Mark paid</button>
                        <button type="button" wire:click="void({{ $selected->id }})" wire:confirm="Void this invoice?" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Void</button>
                    @endif
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="ns-pg__act ns-pg__act--link"><x-nx.icon name="globe" /> Public link</a>
                </div>
            @else
                <div class="ns-pg__panelempty"><x-nx.icon name="file" /> Select an invoice to see its details.</div>
            @endif
        </div>
    </div>

    {{-- Create-invoice sheet --}}
    <div x-show="create" x-cloak class="ns-modal" x-trap.noscroll="create" @keydown.escape.window="create = false" role="dialog" aria-modal="true" aria-label="Create invoice">
        <div class="ns-scrim" @click="create = false"></div>
        <div class="ns-sheet">
            <div class="ns-handle"></div>
            <div class="ns-sheet__head">
                <span class="ns-tile"><x-nx.icon name="receipt" /></span>
                <div class="ns-text"><b>Create invoice</b><small>Saved as a draft. Send it when you're ready.</small></div>
                <button type="button" class="ns-sheet__close" @click="create = false" aria-label="Close"><x-nx.icon name="x" /></button>
            </div>
            <div class="ns-sheet__body">
                <div class="ns-pg__form">
                    <label class="ns-pg__lbl" for="inv-client">Client</label>
                    <select id="inv-client" wire:model="clientId" class="ns-input">
                        <option value="">Choose a client…</option>
                        @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @error('clientId')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror

                    <label class="ns-pg__lbl" for="inv-desc">Description</label>
                    <input id="inv-desc" type="text" wire:model="description" placeholder="1-month eSIM renewal" class="ns-input">
                    @error('description')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror

                    <div class="ns-pg__two">
                        <div>
                            <label class="ns-pg__lbl" for="inv-amt">Amount ($)</label>
                            <input id="inv-amt" type="number" step="0.01" wire:model="amount" class="ns-input">
                            @error('amount')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label class="ns-pg__lbl" for="inv-due">Due date</label>
                            <input id="inv-due" type="date" wire:model="dueAt" class="ns-input">
                        </div>
                    </div>
                    @if ($error)<p class="ns-pg__err" role="alert">{{ $error }}</p>@endif
                </div>
            </div>
            <div class="ns-sheet__foot" style="padding:12px 16px 24px">
                <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create" class="ns-cta">
                    <span wire:loading.remove wire:target="create" class="ns-cta__label">Create invoice</span>
                    <span wire:loading wire:target="create" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                </button>
            </div>
        </div>
    </div>
</x-nx.page>
</div>
