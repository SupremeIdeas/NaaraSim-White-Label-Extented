<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\SidebarMenu as SidebarMenuStore;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Sidebar menu (BUILD-3 §7). Manage the global glass sidebar: the
 * reviews URL, the links display mode, the blog widget, and any custom links.
 * The mandatory legal/compliance + account-deletion items are always present in
 * the sidebar itself and aren't editable here (they can't be removed).
 */
#[Layout('components.layouts.admin')]
class SidebarMenu extends Component
{
    public string $reviews_url = '';

    public string $display_mode = 'list';

    public bool $blog_widget = false;

    /** @var array<int, array{label:string, icon:string, url:string}> */
    public array $links = [];

    public string $new_label = '';

    public string $new_icon = '';

    public string $new_url = '';

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);

        $this->reviews_url = (string) (SidebarMenuStore::reviewsUrl() ?? '');
        $this->display_mode = SidebarMenuStore::displayMode();
        $this->blog_widget = SidebarMenuStore::blogWidgetEnabled();
        $this->links = SidebarMenuStore::customLinks();
    }

    public function saveSettings(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'reviews_url' => ['nullable', 'url', 'max:255'],
            'display_mode' => ['required', 'in:list,grid'],
        ]);

        Setting::setValue(SidebarMenuStore::REVIEWS_KEY, trim($this->reviews_url), 'sidebar');
        Setting::setValue(SidebarMenuStore::MODE_KEY, $this->display_mode, 'sidebar');
        Setting::setValue(SidebarMenuStore::BLOG_KEY, $this->blog_widget, 'sidebar');
        SidebarMenuStore::flush();

        Auditor::log('sidebar.settings_updated');
        $this->saved = 'Sidebar settings saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Sidebar settings saved.');
    }

    public function addLink(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'new_label' => ['required', 'string', 'max:60'],
            'new_url' => ['required', 'url', 'max:255'],
            'new_icon' => ['nullable', 'string', 'max:40'],
        ]);

        $this->links[] = [
            'label' => trim($this->new_label),
            'icon' => trim($this->new_icon) ?: 'chevron-right',
            'url' => trim($this->new_url),
        ];
        $this->persistLinks();
        $this->reset(['new_label', 'new_icon', 'new_url']);
        $this->dispatch('nx-toast', type: 'success', message: 'Link added.');
    }

    public function removeLink(int $i): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        if (isset($this->links[$i])) {
            unset($this->links[$i]);
            $this->links = array_values($this->links);
            $this->persistLinks();
        }
    }

    private function persistLinks(): void
    {
        Setting::setValue(SidebarMenuStore::LINKS_KEY, $this->links, 'sidebar');
        SidebarMenuStore::flush();
    }

    public function render()
    {
        return view('livewire.admin.sidebar-menu', [
            'legalLinks' => SidebarMenuStore::legalLinks(),
        ]);
    }
}
