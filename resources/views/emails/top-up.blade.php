@php
    $sym = $currency === 'USD' ? '$' : $currency.' ';
    $amountStr = $sym.number_format($amount, 2);
    $balanceStr = $sym.number_format($newBalance, 2);
@endphp
<x-mail.layout template-key="top-up" heading="Wallet topped up">
    @if($__intro = \App\Support\MailTemplates::intro('top-up'))<p style="margin:0 0 12px;">{{ $__intro }}</p>@endif
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">Your top-up went through{{ $name ? ', '.$name : '' }}</p>
    <p style="margin:0 0 4px;">We’ve added your payment to your {{ config('app.name') }} wallet. It’s ready to spend on eSIMs and numbers straight away.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border:1px solid #e2e8f0;border-radius:12px;">
        <tr>
            <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">Amount added</td>
            <td align="right" style="padding:14px 16px;border-bottom:1px solid #e2e8f0;font-weight:700;color:#0A6E6E;">{{ $amountStr }}</td>
        </tr>
        <tr>
            <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">Paid with</td>
            <td align="right" style="padding:14px 16px;border-bottom:1px solid #e2e8f0;font-weight:700;">{{ $gateway }}</td>
        </tr>
        <tr>
            <td style="padding:14px 16px;color:#64748b;font-size:13px;">New {{ $currency }} balance</td>
            <td align="right" style="padding:14px 16px;font-weight:700;">{{ $balanceStr }}</td>
        </tr>
    </table>

    <x-mail.button template-key="top-up" :url="$url">Go to my wallet</x-mail.button>
    <p style="margin:0;color:#64748b;font-size:13px;">This is your receipt. If you didn’t make this top-up, contact us right away from the Help option in the app.</p>
</x-mail.layout>
