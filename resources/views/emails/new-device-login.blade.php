<x-mail.layout heading="New sign-in to your account">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">New sign-in to your account</p>
    <p style="margin:0 0 4px;">We noticed a sign-in to your {{ config('app.name') }} account from a device we haven't seen before, on {{ $when }}.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border:1px solid #e2e8f0;border-radius:12px;">
        <tr>
            <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">IP address</td>
            <td align="right" style="padding:14px 16px;border-bottom:1px solid #e2e8f0;font-weight:700;">{{ $ipAddress ?: 'Unknown' }}</td>
        </tr>
        <tr>
            <td style="padding:14px 16px;color:#64748b;font-size:13px;">Device</td>
            <td align="right" style="padding:14px 16px;font-weight:700;">{{ $userAgent ?: 'Unknown' }}</td>
        </tr>
    </table>

    <p style="margin:16px 0 0;padding:12px 16px;background-color:#fef2f2;border-radius:10px;color:#991b1b;font-size:14px;">
        If this was you, no action is needed. If you don't recognise this sign-in, change your password immediately and turn on two-factor authentication.
    </p>
    <x-mail.button :url="$url">Review security</x-mail.button>
</x-mail.layout>
