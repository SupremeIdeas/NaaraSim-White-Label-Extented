@php
    $isEsim = $product === 'esim';
    $amountStr = ($currency === 'USD' ? '$' : $currency.' ').number_format($amount, 2);
@endphp
<x-mail.layout template-key="order-placed" :heading="$isEsim ? 'Your eSIM order is confirmed' : 'Your number is on the way'">
    @if($__intro = \App\Support\MailTemplates::intro('order-placed'))<p style="margin:0 0 12px;">{{ $__intro }}</p>@endif
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">Thanks{{ $name ? ', '.$name : '' }} — your order is confirmed</p>
    <p style="margin:0 0 4px;">
        @if ($isEsim)
            Your eSIM is being provisioned now. It’ll appear on your dashboard shortly with the QR code and manual activation code, ready to install before you travel.
        @else
            We’re reserving your number now. Your verification code will appear on your dashboard as soon as it arrives — and if it doesn’t come in time, we automatically refund you.
        @endif
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border:1px solid #e2e8f0;border-radius:12px;">
        <tr>
            <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">{{ $isEsim ? 'eSIM plan' : 'Service' }}</td>
            <td align="right" style="padding:14px 16px;border-bottom:1px solid #e2e8f0;font-weight:700;">{{ $itemName }}</td>
        </tr>
        <tr>
            <td style="padding:14px 16px;color:#64748b;font-size:13px;">Amount paid</td>
            <td align="right" style="padding:14px 16px;font-weight:700;color:#0A6E6E;">{{ $amountStr }}</td>
        </tr>
    </table>

    <x-mail.button template-key="order-placed" :url="$url">View on my dashboard</x-mail.button>
    <p style="margin:0;color:#64748b;font-size:13px;">Charged securely from your {{ config('app.name') }} wallet. Questions? Just reply to this email.</p>
</x-mail.layout>
