<x-mail.layout heading="Number blocked">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">Thanks{{ $name ? ', '.$name : '' }} — that number is now blocked</p>
    <p style="margin:0 0 4px;"><strong>{{ $phoneNumber }}</strong> has been reported by enough {{ config('app.name') }} users that we've blocked it platform-wide. No one on {{ config('app.name') }} will be able to call it going forward.</p>
    <p style="margin:16px 0 0;color:#64748b;font-size:13px;">Thank you for helping keep the community safe from scam and premium-rate numbers.</p>
    <x-mail.button :url="$url">View my contacts</x-mail.button>
</x-mail.layout>
