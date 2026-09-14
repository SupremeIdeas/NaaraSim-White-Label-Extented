<x-mail.layout heading="Shared plan invite">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">You've been invited to a shared plan{{ $name ? ', '.$name : '' }}</p>
    <p style="margin:0 0 4px;"><strong>{{ $ownerName }}</strong> has invited you to spend from their {{ config('app.name') }} wallet, up to a cap they've set. You can review and accept (or decline) from your wallet page.</p>
    <x-mail.button :url="$url">Review invite</x-mail.button>
</x-mail.layout>
