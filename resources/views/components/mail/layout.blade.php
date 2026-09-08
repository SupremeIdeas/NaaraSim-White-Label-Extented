{{-- Branded transactional-email shell (Module 22). Inline styles only — email
     clients strip <style>/external CSS and don't support dark: variants, so we
     use a light, high-contrast brand palette that renders everywhere. Brand:
     Deep Teal #0A6E6E, Warm Gold #D4A017, Midnight Navy #0D1B2A. --}}
@props(['heading' => null, 'accent' => null, 'templateKey' => 'global'])
@php($appName = config('app.name', 'NaaraSim'))
@php($accentColor = $accent ?: \App\Support\MailTemplates::accent($templateKey))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $heading ?? $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background-color:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background-color:{{ $accentColor }};padding:24px 32px;">
                            <p style="margin:0;color:#D4A017;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">Supreme Ideas Agency</p>
                            <p style="margin:4px 0 0;color:#ffffff;font-size:22px;font-weight:700;">{{ $appName }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;color:#0D1B2A;font-size:15px;line-height:1.6;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background-color:#f8fafc;border-top:1px solid #e2e8f0;">
                            <p style="margin:0;color:#64748b;font-size:12px;line-height:1.5;">
                                {{ $appName }} — Stay Connected. No Borders. No Swaps.<br>
                                You received this email because you have an account with {{ $appName }}.
                                If you didn't expect it, you can safely ignore it.
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:16px 0 0;color:#94a3b8;font-size:11px;">&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
            </td>
        </tr>
    </table>
</body>
</html>
