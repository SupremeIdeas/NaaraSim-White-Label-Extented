<x-mail.layout heading="Test email">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">Your email is working</p>
    <p style="margin:0 0 4px;">This is a test message from your {{ config('app.name') }} admin panel. If you're reading it, your outgoing-mail settings are configured correctly and customers will receive their account, order and security emails.</p>
    <p style="margin:16px 0 0;color:#64748b;font-size:13px;">Sent {{ now()->format('j M Y, H:i') }} UTC via the "{{ config('mail.default') }}" mailer.</p>
</x-mail.layout>
