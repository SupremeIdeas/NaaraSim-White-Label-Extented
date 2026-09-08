<x-mail.layout template-key="welcome" heading="Welcome to {{ config('app.name') }}">
    @if($__intro = \App\Support\MailTemplates::intro('welcome'))<p style="margin:0 0 12px;">{{ $__intro }}</p>@endif
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">You're all set{{ $name ? ', '.$name : '' }}</p>
    <p style="margin:0 0 4px;">Your {{ config('app.name') }} account is ready. You can now buy eSIM data plans for 190+ countries and virtual or verification phone numbers — all in one place.</p>
    <x-mail.button template-key="welcome" :url="$url">Go to my dashboard</x-mail.button>
    <p style="margin:0;color:#64748b;font-size:13px;">Need a hand getting started? Just reply to this email or reach us from the Help option in the app.</p>
</x-mail.layout>
