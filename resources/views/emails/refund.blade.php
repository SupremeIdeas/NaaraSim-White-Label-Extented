@php($amountStr = ($currency === 'USD' ? '$' : $currency.' ').number_format($amount, 2))
<x-mail.layout template-key="refund" heading="Refunded to your wallet">
    @if($__intro = \App\Support\MailTemplates::intro('refund'))<p style="margin:0 0 12px;">{{ $__intro }}</p>@endif
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">Your money is back{{ $name ? ', '.$name : '' }}</p>
    <p style="margin:0 0 4px;">We’ve returned <strong>{{ $amountStr }}</strong> to your {{ config('app.name') }} wallet. Wallet credit is spendable straight away on eSIMs and numbers.</p>

    @if ($reason)
        <p style="margin:16px 0 0;padding:12px 16px;background-color:#f0fdfa;border-radius:10px;color:#0f766e;font-size:14px;">
            Reason: {{ $reason }}
        </p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border:1px solid #e2e8f0;border-radius:12px;">
        <tr>
            <td style="padding:14px 16px;color:#64748b;font-size:13px;">Amount refunded</td>
            <td align="right" style="padding:14px 16px;font-weight:700;color:#0A6E6E;">{{ $amountStr }}</td>
        </tr>
    </table>

    <x-mail.button template-key="refund" :url="$url">View my wallet</x-mail.button>
    <p style="margin:0;color:#64748b;font-size:13px;">We only ever charge when we can deliver — if something doesn’t go through, you get your money back automatically. Questions? Just reply.</p>
</x-mail.layout>
