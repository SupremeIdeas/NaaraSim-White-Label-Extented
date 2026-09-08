<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\LinkPreviewSettings;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Link Previews — lets a super_admin/admin swap the Open Graph image
 * shown when a Naara link is pasted into WhatsApp/Slack/iMessage/etc., per
 * context (default site-wide, invoice links, referral links). Same upload
 * discipline as Admin\Branding: WebP/JPG only, kept small so the preview
 * itself loads fast on the receiving end, stored via MediaStorage, and every
 * context ships with a real banner so nothing is ever blank.
 */
#[Layout('components.layouts.admin')]
class LinkPreviews extends Component
{
    use WithFileUploads;

    public $default_image;

    public $invoice_image;

    public $referral_image;

    private function guard(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->guard();
    }

    public function getCurrentProperty(): array
    {
        return LinkPreviewSettings::all();
    }

    public function save(): void
    {
        $this->guard();

        $this->validate([
            'default_image' => 'nullable|mimes:webp,jpg,jpeg,png|max:2048',
            'invoice_image' => 'nullable|mimes:webp,jpg,jpeg,png|max:2048',
            'referral_image' => 'nullable|mimes:webp,jpg,jpeg,png|max:2048',
        ], [], [
            'default_image' => 'default preview image',
            'invoice_image' => 'invoice preview image',
            'referral_image' => 'referral preview image',
        ]);

        $map = [
            'default_image' => LinkPreviewSettings::CONTEXT_DEFAULT,
            'invoice_image' => LinkPreviewSettings::CONTEXT_INVOICE,
            'referral_image' => LinkPreviewSettings::CONTEXT_REFERRAL,
        ];

        foreach ($map as $field => $context) {
            if ($this->{$field}) {
                $url = MediaStorage::storePublic($this->{$field}, 'link-previews');
                Setting::setValue("link_preview.{$context}", $url, 'link_preview');
                $this->{$field} = null;
            }
        }

        LinkPreviewSettings::bust();
        $this->dispatch('nx-toast', type: 'success', message: 'Link preview images updated.');
    }

    /** Reset one context back to its shipped default banner. */
    public function resetContext(string $context): void
    {
        $this->guard();
        abort_unless(in_array($context, LinkPreviewSettings::CONTEXTS, true), 404);

        Setting::setValue("link_preview.{$context}", '', 'link_preview');
        LinkPreviewSettings::bust();
        $this->dispatch('nx-toast', type: 'success', message: 'Reverted to the default image.');
    }

    public function render()
    {
        return view('livewire.admin.link-previews');
    }
}
