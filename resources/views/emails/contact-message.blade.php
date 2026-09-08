<x-mail.layout heading="New contact message">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">New contact-form message</p>
    <p style="margin:0 0 4px;"><b>From:</b> {{ $name }} &lt;{{ $email }}&gt;</p>
    <p style="margin:0 0 12px;"><b>Topic:</b> {{ $topic }}</p>
    <p style="margin:0;padding:12px 16px;background-color:#f8fafc;border-radius:10px;white-space:pre-line;">{{ $body }}</p>
    <p style="margin:16px 0 0;color:#64748b;font-size:13px;">Reply directly to this email to answer them.</p>
</x-mail.layout>
