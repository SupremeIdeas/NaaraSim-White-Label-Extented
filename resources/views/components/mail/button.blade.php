{{-- Email-safe CTA button (table + inline styles for client compatibility).
     Accent colour resolves through Email Studio (per-template → global → brand). --}}
@props(['url', 'accent' => null, 'templateKey' => 'global'])
@php($accentColor = $accent ?: \App\Support\MailTemplates::accent($templateKey))
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
    <tr>
        <td align="center" style="border-radius:10px;background-color:{{ $accentColor }};">
            <a href="{{ $url }}" target="_blank" rel="noopener"
               style="display:inline-block;padding:12px 28px;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;border-radius:10px;">
                {{ $slot }}
            </a>
        </td>
    </tr>
</table>
<p style="margin:0 0 8px;color:#64748b;font-size:12px;line-height:1.5;">
    If the button doesn't work, copy and paste this link into your browser:<br>
    <a href="{{ $url }}" style="color:{{ $accentColor }};word-break:break-all;">{{ $url }}</a>
</p>
