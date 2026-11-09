@php $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; $money = fn ($v) => '$'.number_format((float) $v, 2); @endphp
<div>
<x-nx.page class="ns-tp">
    <div class="ns-tp__hero">
        <span class="ns-tile"><x-nx.icon name="users" /></span>
        <div><h1 class="ns-h1">My people</h1><p class="ns-sub">Save the numbers you top up, and set daily, weekly or monthly auto top-ups for airtime or data.</p></div>
        <a href="{{ route('topup') }}" wire:navigate class="ns-tp__history"><x-nx.icon name="signal" /> New top-up</a>
    </div>
    @if ($notice)<p class="ns-tp__detect" role="status"><x-nx.icon name="check" /> {{ $notice }}</p>@endif
    @if ($problem)<p class="ns-tp__err" role="alert"><x-nx.icon name="alert-triangle" /> {{ $problem }}</p>@endif

    <section class="ns-card ns-tp__step" data-tour="topup-add-person">
        <div class="ns-tp__num"><b>+</b> Add a person</div>
        <div class="ns-tp__row">
            <label class="ns-tp__field"><span class="ns-small">Country</span><select wire:model.live="country" class="ns-input" aria-label="Country">@foreach ($countries as $c => $n)<option value="{{ $c }}">{{ $n }}</option>@endforeach</select></label>
            <label class="ns-tp__field ns-tp__field--grow"><span class="ns-small">Phone number</span><input type="tel" wire:model.live.debounce.500ms="phone" class="ns-input" placeholder="0803 123 4567 or +234…" aria-label="Phone number"></label>
            <label class="ns-tp__field"><span class="ns-small">Name</span><input type="text" wire:model="name" maxlength="60" class="ns-input" placeholder="Mum, Tunde…" aria-label="Name"></label>
        </div>
        @if ($phoneError)<p class="ns-tp__err"><x-nx.icon name="alert-triangle" /> {{ $phoneError }}</p>@endif
        @if ($e164 !== '')
            <div class="ns-tp__found">
                @if ($operator)@include('livewire.partials.topup.logo', ['name' => $operator, 'size' => 44, 'country' => $country])<div><b>{{ $operator }} · {{ $e164 }}</b><span class="ns-small">{{ $netSource === 'prefix' ? 'From the number\'s network code. Change it below if the number has moved networks.' : 'Network confirmed.' }}</span></div>
                @else<div><b>{{ $e164 }}</b><span class="ns-small">We could not tell the network. Pick it below.</span></div>@endif
            </div>
            @if ($netSource === 'prefix' || ! $operator)
                <div class="ns-tp__ops">@foreach ($operators as $op)<button type="button" wire:click="pickOperator(@js($op))" class="ns-tp__op {{ $operator && strcasecmp($operator, $op) === 0 ? 'is-on' : '' }}">@include('livewire.partials.topup.logo', ['name' => $op, 'size' => 36, 'country' => $country])<span>{{ $op }}</span></button>@endforeach</div>
            @endif
        @endif
        <button type="button" class="ns-cta ns-cta--pill" wire:click="add" wire:loading.attr="disabled" wire:target="add" style="justify-self:start"><x-nx.icon name="plus" /> Save person</button>
    </section>

    @forelse ($bens as $b)
        <section class="ns-card ns-tp__step" wire:key="ben{{ $b->id }}">
            <div class="ns-tp__recv">
                @include('livewire.partials.topup.logo', ['name' => $b->operator_name ?? $b->name, 'size' => 48, 'country' => $b->country_iso])
                <div>
                    @if ($editId === $b->id)
                        <div class="ns-tp__row"><input type="text" wire:model="editName" wire:keydown.enter="saveEdit" class="ns-input" maxlength="60" aria-label="Name"><button type="button" class="ns-cta ns-cta--pill" wire:click="saveEdit">Save</button></div>
                    @else
                        <b>{{ $b->name }}</b><br><span class="ns-small">{{ $b->phone_e164 }} · {{ $b->operator_name ?: 'network not set' }} · {{ \App\Support\CountryNames::name($b->country_iso) }}</span>
                    @endif
                </div>
                <a href="{{ route('topup', ['ben' => $b->id]) }}" wire:navigate class="ns-cta ns-cta--pill"><x-nx.icon name="signal" /> Top up</a>
            </div>
            <div class="ns-tp__tabs">
                <button type="button" class="ns-tp__more" wire:click="openPlan({{ $b->id }})">{{ $planFor === $b->id ? 'Close' : 'Set an auto top-up' }}</button>
                <span><button type="button" class="ns-small" wire:click="startEdit({{ $b->id }})" style="margin-right:12px">Rename</button><button type="button" class="ns-small" style="color:rgb(var(--nx-danger,200 50 50))" wire:click="remove({{ $b->id }})" wire:confirm="Remove {{ $b->name }} and stop their auto top-ups?">Remove</button></span>
            </div>

            @foreach ($b->autoReloads as $a)
                <div class="ns-tp__auto" wire:key="plan{{ $a->id }}">
                    <div class="ns-tp__recv"><div><b>{{ $a->product_type === 'data' ? ($a->product_name ?: 'Data') : $a->send_currency.' '.number_format((float) $a->send_amount, 0).' airtime' }}</b><br><span class="ns-small">{{ $a->describe() }}</span></div>
                        <x-nx.pill :variant="$a->active ? 'best' : 'out'">{{ $a->active ? 'Active' : 'Paused' }}</x-nx.pill></div>
                    <p class="ns-small">@if ($a->active && $a->next_run_at)Next: {{ $a->next_run_at->setTimezone($a->timezone)->format('D j M, H:i') }}. @endif @if ($a->last_run_at)Last run {{ $a->last_run_at->diffForHumans() }}{{ $a->last_status ? ' ('.str_replace('_', ' ', $a->last_status).')' : '' }}. @endif @if ($a->paused_reason)<span style="color:rgb(var(--nx-danger,200 50 50))">Paused: {{ $a->paused_reason }}</span>@endif</p>
                    <div class="ns-tp__quick"><button type="button" class="ns-tp__chip" wire:click="togglePlan({{ $a->id }})">{{ $a->active ? 'Pause' : 'Resume' }}</button><button type="button" class="ns-tp__chip" wire:click="removePlan({{ $a->id }})" wire:confirm="Delete this auto top-up?">Delete</button></div>
                </div>
            @endforeach

            @if ($planFor === $b->id)
                <div class="ns-tp__auto">
                    <x-nx.segmented :options="['airtime' => 'Airtime', 'data' => 'Data bundle']" :current="$planType" action="setPlanType" label="Type" />
                    <div class="ns-tp__row">
                        <label class="ns-tp__field ns-tp__field--grow"><span class="ns-small">What to send</span>
                            <select wire:model.live="planProduct" class="ns-input" aria-label="Offer"><option value="">Choose…</option>
                                @foreach ($planOptions as $p)<option value="{{ $p->id }}">{{ $p->is_fixed ? ($p->type === 'data' ? $p->name.' · ' : '').$p->currency.' '.number_format((float) $p->fixed_amount, 0).' · '.($prices[$p->id] ? $money($prices[$p->id]) : '') : 'Any amount ('.number_format((float) $p->min_amount).'–'.number_format((float) $p->max_amount).' '.$p->currency.')' }}</option>@endforeach</select></label>
                        @if ($planProduct && ($pp = $planOptions->firstWhere('id', $planProduct)) && ! $pp->is_fixed)<label class="ns-tp__field"><span class="ns-small">Amount ({{ $pp->currency }})</span><input type="number" wire:model="planAmount" class="ns-input" aria-label="Amount"></label>@endif
                    </div>
                    <div class="ns-tp__row">
                        <label class="ns-tp__field"><span class="ns-small">How often</span><select wire:model.live="planFreq" class="ns-input"><option value="daily">Every day</option><option value="weekly">Every week</option><option value="monthly">Every month</option></select></label>
                        @if ($planFreq === 'weekly')<label class="ns-tp__field"><span class="ns-small">On</span><select wire:model="planWeekday" class="ns-input">@foreach ($days as $i => $d)<option value="{{ $i }}">{{ $d }}</option>@endforeach</select></label>@endif
                        @if ($planFreq === 'monthly')<label class="ns-tp__field"><span class="ns-small">On day</span><select wire:model="planMonthday" class="ns-input">@for ($d = 1; $d <= 28; $d++)<option value="{{ $d }}">{{ $d }}</option>@endfor</select></label>@endif
                        <label class="ns-tp__field"><span class="ns-small">At</span><select wire:model="planHour" class="ns-input">@for ($h = 0; $h < 24; $h++)<option value="{{ $h }}">{{ str_pad((string) $h, 2, '0', STR_PAD_LEFT) }}:00</option>@endfor</select></label>
                    </div>
                    <p class="ns-small ns-tp__lock"><x-nx.icon name="info" /> Charged from your wallet at the price on the day. If the wallet is short we skip it and tell you; after 3 problems in a row it pauses. Up to {{ $limit }} active.</p>
                    <button type="button" class="ns-cta ns-cta--pill" wire:click="createPlan" wire:loading.attr="disabled" wire:target="createPlan" style="justify-self:start">Start auto top-up</button>
                </div>
            @endif
        </section>
    @empty
        <x-nx.empty icon="users" title="No saved people yet" text="Add a number above, or tick Save this number when you top up." />
    @endforelse
</x-nx.page>
</div>
