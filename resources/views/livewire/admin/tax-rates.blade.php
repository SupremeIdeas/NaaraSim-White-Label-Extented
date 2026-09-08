<div class="mx-auto max-w-xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Tax / VAT rates</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Set a tax percent per country (ISO code, e.g. GB, DE). Tax is added on top of the price and shown as its own line on checkout and receipts — <strong>only</strong> for the countries you list here. Leave empty to charge no tax anywhere. Whether to charge tax in a country is a legal/accounting decision, not a technical one.</p>
    </div>

    @if ($saved)
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-950/30 dark:text-green-300">{{ $saved }}</div>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <div class="space-y-2">
            @foreach ($rows as $i => $row)
                <div wire:key="tax-{{ $i }}" class="flex items-center gap-2">
                    <input wire:model="rows.{{ $i }}.country" placeholder="ISO (GB)" maxlength="2"
                           class="w-28 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm uppercase dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <div class="relative flex-1">
                        <input wire:model="rows.{{ $i }}.rate" type="number" step="0.001" min="0" max="100" placeholder="Rate"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 pr-8 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <span class="pointer-events-none absolute right-3 top-2 text-sm text-slate-400">%</span>
                    </div>
                    <button wire:click="removeRow({{ $i }})" class="text-slate-400 hover:text-red-500"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
            @endforeach
        </div>
        <button wire:click="addRow" class="mt-3 text-sm font-semibold text-primary hover:underline">+ Add country</button>

        <div class="mt-5 border-t border-slate-100 pt-4 dark:border-white/5">
            <button wire:click="save" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Save tax rates</button>
        </div>
    </div>
</div>
