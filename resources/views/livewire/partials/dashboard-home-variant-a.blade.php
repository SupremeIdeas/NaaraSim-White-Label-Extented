{{--
    Dashboard home — VARIANT A (Theme Batch 2 §2). The baseline composition:
    byte-for-byte the order that shipped before the theme program (welcome/
    marketing-first). naara-official and every theme that hasn't opted into
    another skeleton renders this. It only re-orders the shared block partials —
    identical data, identical markup.
--}}
@include('livewire.partials.dashboard._hero')
@include('livewire.partials.dashboard._greeting')
@include('livewire.partials.dashboard._coupon')
@include('livewire.partials.dashboard._banners')
@include('livewire.partials.dashboard._wallet')
@include('livewire.partials.dashboard._showcase')
@include('livewire.partials.dashboard._lines')
@include('livewire.partials.dashboard._analytics')
