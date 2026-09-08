<x-mail.layout heading="Your password was changed">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">Your password was changed</p>
    <p style="margin:0 0 4px;">This is a confirmation that the password for your {{ config('app.name') }} account was just changed{{ $when ? ' on '.$when : '' }}.</p>
    <p style="margin:16px 0 0;padding:12px 16px;background-color:#fef2f2;border-radius:10px;color:#991b1b;font-size:14px;">
        If this wasn't you, reset your password immediately and turn on two-factor authentication from your Security settings.
    </p>
</x-mail.layout>
