<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\MailTemplates;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Email Studio (NAARA-BUILD-20 §3). Template editor with live preview
 * (§3.1) and the broadcast tool (§3.2, in EmailStudioBroadcast — this component
 * hosts the tabs). super_admin/admin only.
 */
#[Layout('components.layouts.admin')]
class EmailStudio extends Component
{
    public string $tab = 'templates';

    /** Selected template key ('global' for cross-template accent, else a view key). */
    public string $key = 'global';

    public array $form = ['subject' => '', 'heading' => '', 'intro' => '', 'button_text' => '', 'accent_color' => ''];

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->loadTemplate('global');
    }

    public function loadTemplate(string $key): void
    {
        $this->key = ($key === MailTemplates::GLOBAL_KEY || array_key_exists($key, MailTemplates::templates())) ? $key : 'global';
        $o = MailTemplates::override($this->key);
        $this->form = [
            'subject' => (string) ($o['subject'] ?? ''),
            'heading' => (string) ($o['heading'] ?? ''),
            'intro' => (string) ($o['intro'] ?? ''),
            'button_text' => (string) ($o['button_text'] ?? ''),
            'accent_color' => (string) ($o['accent_color'] ?? ''),
        ];
        $this->saved = null;
    }

    public function save(): void
    {
        $this->validate([
            'form.subject' => 'nullable|string|max:150',
            'form.heading' => 'nullable|string|max:150',
            'form.intro' => 'nullable|string|max:1000',
            'form.button_text' => 'nullable|string|max:60',
            'form.accent_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        MailTemplates::save($this->key, $this->form);
        Auditor::log('mail.template_updated', null, null, ['key' => $this->key]);
        $this->saved = $this->key;
        $this->dispatch('nx-toast', type: 'success', message: 'Template saved — live on the next email.');
    }

    /** Render the selected template's email with sample data + the UNSAVED edits. */
    public function previewHtml(): string
    {
        $templates = MailTemplates::templates();
        // 'global' has no view of its own — preview accent on the verification email.
        $viewKey = $this->key === MailTemplates::GLOBAL_KEY ? 'verify' : $this->key;
        $tpl = $templates[$viewKey] ?? $templates['verify'];

        MailTemplates::preview($this->key, $this->form);
        try {
            return view($tpl['view'], $tpl['sample'])->render();
        } catch (\Throwable $e) {
            return '<p style="font-family:sans-serif;color:#b91c1c;padding:16px;">Preview error: '.e($e->getMessage()).'</p>';
        } finally {
            MailTemplates::preview(null, null);
        }
    }

    public function render()
    {
        return view('livewire.admin.email-studio', [
            'templates' => MailTemplates::templates(),
            'previewHtml' => $this->previewHtml(),
        ]);
    }
}
