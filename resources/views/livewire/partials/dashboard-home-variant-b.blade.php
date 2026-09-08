{{--
    Dashboard home — VARIANT B (Theme Batch 2 §2). A "utility-first" composition
    for the data-forward personas (Ledger, Grid Nine, Slate Signal, Capable):
    the wallet balance and the connectivity summary lead, so a returning user
    lands on their state first; the welcome/marketing blocks fall below. SAME
    block partials, SAME data, SAME Livewire component — only the order differs.
--}}
@include('livewire.partials.dashboard._hero')
@include('livewire.partials.dashboard._wallet')
@include('livewire.partials.dashboard._lines')
@include('livewire.partials.dashboard._analytics')
@include('livewire.partials.dashboard._showcase')
@include('livewire.partials.dashboard._greeting')
@include('livewire.partials.dashboard._coupon')
@include('livewire.partials.dashboard._banners')
