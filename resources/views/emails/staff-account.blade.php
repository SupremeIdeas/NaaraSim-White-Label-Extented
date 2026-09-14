<x-mail.layout :heading="$heading">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">{{ $heading }}{{ $name ? ', '.$name : '' }}</p>
    @switch ($action)
        @case ('granted')
            <p style="margin:0 0 4px;">An admin has given you staff access to {{ config('app.name') }}.</p>
            @if (count($scopes))
                <p style="margin:12px 0 0;color:#64748b;font-size:13px;">Your access covers: {{ implode(', ', $scopes) }}.</p>
            @endif
            @break
        @case ('scopes_updated')
            <p style="margin:0 0 4px;">An admin has updated what you can access on {{ config('app.name') }}.</p>
            @if (count($scopes))
                <p style="margin:12px 0 0;color:#64748b;font-size:13px;">Your access now covers: {{ implode(', ', $scopes) }}.</p>
            @else
                <p style="margin:12px 0 0;color:#64748b;font-size:13px;">Your access no longer covers any staff areas.</p>
            @endif
            @break
        @case ('revoked')
            <p style="margin:0 0 4px;">Your staff access to {{ config('app.name') }} has been removed. Your regular account still works exactly as before.</p>
            @break
        @case ('removed')
            <p style="margin:0 0 4px;">Your staff account on {{ config('app.name') }} has been permanently removed.</p>
            @break
    @endswitch
    @unless ($action === 'removed')
        <x-mail.button :url="$url">Account settings</x-mail.button>
    @endunless
</x-mail.layout>
