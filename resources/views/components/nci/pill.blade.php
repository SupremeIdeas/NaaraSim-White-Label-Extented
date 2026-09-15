@props(['kind' => 'status', 'value' => null])
@php
    $v = (string) $value;
    // Semantic colour by state — scannable at a glance (BUILD-17 §3). Green =
    // healthy, amber = watch, red = problem, slate = inert/unknown.
    $map = match ($kind) {
        'status' => ['ok' => 'green', 'low' => 'amber', 'down' => 'red', 'configured' => 'slate', 'coming_soon' => 'slate', 'paused' => 'slate'],
        'circuit' => ['closed' => 'green', 'half_open' => 'amber', 'open' => 'red'],
        'risk' => ['low' => 'green', 'medium' => 'amber', 'high' => 'red'],
        default => [],
    };
    $tone = $map[$v] ?? 'slate';
    $classes = [
        'green' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'slate' => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
    ][$tone];
    $label = $v === '' ? '—' : ucfirst(str_replace('_', ' ', $v));
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold $classes"]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ ['green' => 'bg-green-500', 'amber' => 'bg-amber-500', 'red' => 'bg-red-500', 'slate' => 'bg-slate-400'][$tone] }}"></span>
    {{ $label }}
</span>
