<x-mail.layout :heading="$enabled ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled'">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">{{ $enabled ? 'Two-factor authentication is on' : 'Two-factor authentication is off' }}</p>
    @if ($enabled)
        <p style="margin:0 0 4px;">This confirms two-factor authentication was turned on for your {{ config('app.name') }} account on {{ $when }}. You'll now need a code from your authenticator app to sign in.</p>
    @else
        <p style="margin:0 0 4px;">This confirms two-factor authentication was turned off for your {{ config('app.name') }} account on {{ $when }}.</p>
        <p style="margin:16px 0 0;padding:12px 16px;background-color:#fef2f2;border-radius:10px;color:#991b1b;font-size:14px;">
            If this wasn't you, change your password immediately and turn two-factor authentication back on from your Security settings.
        </p>
    @endif
</x-mail.layout>
