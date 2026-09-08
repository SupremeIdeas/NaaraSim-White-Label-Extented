<x-mail.layout :heading="$subject" template-key="global">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">{{ $subject }}</p>
    <div style="font-size:15px;line-height:1.6;">{!! $bodyHtml !!}</div>
</x-mail.layout>
