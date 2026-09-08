<x-mail.layout template-key="reset" heading="Reset your password">
    @if($__intro = \App\Support\MailTemplates::intro('reset'))<p style="margin:0 0 12px;">{{ $__intro }}</p>@endif
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">Reset your password</p>
    <p style="margin:0 0 4px;">We received a request to reset the password for your {{ config('app.name') }} account. Click below to choose a new one.</p>
    <x-mail.button template-key="reset" :url="$url">Reset my password</x-mail.button>
    <p style="margin:0;color:#64748b;font-size:13px;">This link expires in {{ $expires }} minutes. If you didn't request a reset, no action is needed — your password stays the same.</p>
</x-mail.layout>
