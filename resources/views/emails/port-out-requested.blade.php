<x-mail.layout heading="Port-out requested">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">We've got your port-out request{{ $name ? ', '.$name : '' }}</p>
    <p style="margin:0 0 4px;">You asked to take <strong>{{ $phoneNumber }}</strong> to another carrier. Our team will be in touch with everything your new carrier needs to complete the transfer.</p>
    <p style="margin:16px 0 0;padding:12px 16px;background-color:#f0fdfa;border-radius:10px;color:#0f766e;font-size:14px;">
        We will never block or delay a port-out — {{ $phoneNumber }} stays active on {{ config('app.name') }} until your new carrier's transfer completes.
    </p>
    <x-mail.button :url="$url">View my lines</x-mail.button>
</x-mail.layout>
