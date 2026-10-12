{{-- Notification settings: per kind of message, which channels may be used, plus quiet hours. Skin tokens only. --}}
<div>
<x-nx.page class="ns-narrow">
    <div>
        <a href="{{ route('notifications') }}" wire:navigate class="text-sm text-[rgb(var(--nx-text-2))] hover:underline">Notifications</a>
        <h1 class="ns-h1" style="margin-top:6px">Notification settings</h1>
        <p class="ns-sub">Choose how we reach you. Security and billing notices always appear in your bell; you choose whether they also push or email.</p>
    </div>

    <form wire:submit="save" class="mt-5 space-y-4">
        <div class="overflow-x-auto rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
            <table class="w-full min-w-[420px] text-sm">
                <thead>
                    <tr class="border-b border-[rgb(var(--nx-line))] text-left text-xs text-[rgb(var(--nx-text-2))]">
                        <th class="px-4 py-3 font-medium">Kind of message</th>
                        <th class="px-2 py-3 text-center font-medium">In app</th>
                        <th class="px-2 py-3 text-center font-medium">Push</th>
                        <th class="px-2 py-3 text-center font-medium">Email</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $cat => $meta)
                        <tr wire:key="pref-{{ $cat }}" class="border-b border-[rgb(var(--nx-line))] last:border-0">
                            <td class="px-4 py-3 text-[rgb(var(--nx-text))]">{{ $meta['label'] }}@if (! empty($meta['critical']))<span class="ml-2 text-[11px] text-[rgb(var(--nx-text-2))]">always on in app</span>@endif</td>
                            @foreach (['in_app', 'push', 'email'] as $ch)
                                <td class="px-2 py-3 text-center">
                                    <input type="checkbox" wire:model="channels.{{ $cat }}.{{ $ch }}" @disabled($ch === 'in_app' && ! empty($meta['critical'])) aria-label="{{ $meta['label'] }}: {{ str_replace('_', ' ', $ch) }}" class="h-4 w-4">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Quiet hours</h2>
            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Push notifications are held back during this window ({{ $tz }} time). Your bell still collects everything, and security alerts are never held.</p>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-[rgb(var(--nx-text))]">
                <label class="flex items-center gap-2">From <input type="time" wire:model="quiet_start" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5"></label>
                <label class="flex items-center gap-2">To <input type="time" wire:model="quiet_end" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5"></label>
            </div>
            @error('quiet_start')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
            @error('quiet_end')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
        </div>

        <div class="flex justify-end"><button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta ns-cta--pill">Save settings</button></div>
    </form>
</x-nx.page>
</div>
