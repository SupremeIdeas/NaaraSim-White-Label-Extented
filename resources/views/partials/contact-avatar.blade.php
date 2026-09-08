@php($__c = $contact->avatarColor())
{{-- Inline hex gradient — the colour is chosen at runtime, so Tailwind's JIT
     would purge dynamic `from-*/to-*` class names. Style attribute survives. --}}
<span class="flex shrink-0 items-center justify-center rounded-full font-bold text-white {{ $size ?? 'h-10 w-10 text-sm' }}"
      style="background-image: linear-gradient(135deg, {{ $__c['from'] }}, {{ $__c['to'] }})"
      aria-hidden="true">{{ $contact->initials() }}</span>
