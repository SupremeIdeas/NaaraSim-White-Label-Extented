@php
    // Generate the QR inline from the LPA so it renders even when the mail client
    // blocks remote images; fall back to the provider's hosted QR image URL.
    $qrCid = $lpa ? $message->embedData(\App\Support\Niche\EsimQr::png($lpa), 'esim-qr.png', 'image/png') : null;
@endphp
<x-mail.layout heading="Your eSIM is ready to install">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">Hi{{ $clientName ? ', '.$clientName : '' }} —</p>
    <p style="margin:0 0 16px;">{{ $brand }} has set up your <strong>{{ $planName }}</strong> eSIM. Install it before you travel using the QR code or the manual code below.</p>

    @if ($qrCid || $qrCodeUrl)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;">
            <tr><td align="center">
                <img src="{{ $qrCid ?: $qrCodeUrl }}" alt="eSIM QR code" width="220" height="220" style="border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:6px;">
                <p style="margin:8px 0 0;color:#64748b;font-size:13px;">Scan this from another device to install.</p>
            </td></tr>
        </table>
    @endif

    @if ($universalLink)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
            <tr><td align="center">
                <a href="{{ $universalLink }}" style="display:inline-block;background:#0A6E6E;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:10px;">Tap to install on iPhone</a>
                <p style="margin:8px 0 0;color:#94a3b8;font-size:12px;">iPhone (iOS 17.4+). On Android, use the manual code below.</p>
            </td></tr>
        </table>
    @endif

    @if ($lpa)
        <p style="margin:0 0 6px;color:#64748b;font-size:13px;">Manual activation code</p>
        <p style="margin:0 0 18px;font-family:monospace;font-size:13px;word-break:break-all;background:#f1f5f9;border-radius:8px;padding:12px;">{{ $lpa }}</p>
    @endif

    <p style="margin:0 0 6px;font-weight:700;">How to install</p>
    <ol style="margin:0 0 8px;padding-left:18px;color:#334155;font-size:14px;">
        @foreach ($steps as $step)
            <li style="margin:0 0 6px;">{{ $step }}</li>
        @endforeach
    </ol>

    <p style="margin:16px 0 0;color:#94a3b8;font-size:12px;">Sent by {{ $brand }} via NaaraSim. Keep this code private — it activates your plan only once.</p>
</x-mail.layout>
