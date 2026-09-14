<x-mail.layout :heading="$approved ? 'Identity verified' : 'Verification unsuccessful'">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">{{ $approved ? 'You\'re verified' : 'We couldn\'t verify you' }}{{ $name ? ', '.$name : '' }}</p>
    @if ($approved)
        <p style="margin:0 0 4px;">Your identity has been verified at level {{ $level }}. This may unlock higher limits and additional features on your {{ config('app.name') }} account.</p>
    @else
        <p style="margin:0 0 4px;">We weren't able to verify your identity this time{{ $reason ? ' — '.$reason : '.' }}</p>
        <p style="margin:12px 0 0;color:#64748b;font-size:13px;">You can try again from your account, or reach out if you think this is a mistake.</p>
    @endif
    <x-mail.button :url="$url">{{ $approved ? 'View my account' : 'Try again' }}</x-mail.button>
</x-mail.layout>
