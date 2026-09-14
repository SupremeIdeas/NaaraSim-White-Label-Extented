<x-mail.layout :heading="$heading">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">{{ $heading }}{{ $name ? ', '.$name : '' }}</p>
    @switch ($action)
        @case ('deactivated')
            <p style="margin:0 0 4px;">Your {{ config('app.name') }} account has been deactivated at your request. You can sign back in at any time to reactivate it — nothing has been deleted.</p>
            @break
        @case ('reactivated')
            <p style="margin:0 0 4px;">Your {{ config('app.name') }} account is active again. Everything is exactly as you left it.</p>
            @break
        @case ('deletion_requested')
            <p style="margin:0 0 4px;">We've received your request to permanently delete your {{ config('app.name') }} account and data. A super admin will review this request before anything is erased.</p>
            <p style="margin:12px 0 0;color:#64748b;font-size:13px;">Changed your mind? You can withdraw this request from your account settings any time before it's approved.</p>
            @break
        @case ('deletion_cancelled')
            <p style="margin:0 0 4px;">You've withdrawn your account deletion request. Your {{ config('app.name') }} account and data remain exactly as they are.</p>
            @break
        @case ('erased')
            <p style="margin:0 0 4px;">As requested, your {{ config('app.name') }} account and personal data have been permanently erased. This confirmation is the last email you'll receive from us.</p>
            @break
    @endswitch
    @unless ($action === 'erased')
        <x-mail.button :url="$url">Account settings</x-mail.button>
    @endunless
</x-mail.layout>
