{{-- Reusable ambient glow (BLUEPRINT-ambient-gradient §1/§3). Wraps any section
     in the same soft brand-aurora the hero uses — light + dark tuned, brand-token
     driven, reduced-motion safe (all in .nx-ambient, sections.css). Drop it around
     product carousels, About, pricing, testimonials — not dense tables/forms. --}}
<div {{ $attributes->merge(['class' => 'nx-ambient']) }}>
    {{ $slot }}
</div>
