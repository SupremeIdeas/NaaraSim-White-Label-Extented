<?php

namespace App\Livewire\Admin;

use App\Models\DistributedPackage;
use App\Models\Setting;
use App\Models\WhiteLabelApiLog;
use App\Models\WhiteLabelInstance;
use App\Services\Updater\PackagePublisher;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → White-Label Oversight (Batch 4 §2). The central view of who is a
 * registered white-label brand, what tier/status they're on, and — per brand —
 * every call their deployed copy has made to the distribution API. Plus the
 * publisher-side controls: which built packages are actually published to
 * instances, and the API feature flag.
 *
 * super_admin/admin gate, like every other sensitive admin screen. Registration
 * and activation of instances themselves is Batch 6's license-issuance flow —
 * this screen is oversight + package publishing, not brand onboarding.
 */
#[Layout('components.layouts.admin')]
class WhiteLabelRegistry extends Component
{
    /** The brand whose API-call history is being drilled into, if any. */
    public ?int $selectedInstanceId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function getApiEnabledProperty(): bool
    {
        return (bool) Setting::getValue('white_label_api.enabled', false);
    }

    public function toggleApi(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $new = ! $this->apiEnabled;
        Setting::setValue('white_label_api.enabled', $new);
        Auditor::log('white_label_api.toggled', null, null, ['enabled' => $new]);
        $this->dispatch('nx-toast', type: 'success', message: 'White-label API '.($new ? 'enabled' : 'disabled').'.');
    }

    public function selectInstance(int $id): void
    {
        $this->selectedInstanceId = $this->selectedInstanceId === $id ? null : $id;
    }

    public function togglePublish(int $packageId, PackagePublisher $publisher): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $package = DistributedPackage::find($packageId);
        if ($package === null) {
            return;
        }

        $publisher->setPublished($package, ! $package->is_published, Auth::id());
        $this->dispatch('nx-toast', type: 'success', message: 'Package '.($package->fresh()->is_published ? 'published' : 'unpublished').'.');
    }

    public function withdrawPackage(int $packageId, PackagePublisher $publisher): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $package = DistributedPackage::find($packageId);
        if ($package === null) {
            return;
        }

        $publisher->withdraw($package, Auth::id());
        $this->dispatch('nx-toast', type: 'success', message: 'Package withdrawn from distribution.');
    }

    public function getInstancesProperty()
    {
        return WhiteLabelInstance::query()->latest()->get();
    }

    public function getPackagesProperty()
    {
        return DistributedPackage::query()->latest()->get();
    }

    public function getSelectedLogsProperty()
    {
        if ($this->selectedInstanceId === null) {
            return collect();
        }

        return WhiteLabelApiLog::query()
            ->where('white_label_instance_id', $this->selectedInstanceId)
            ->latest('created_at')
            ->limit(50)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.white-label-registry');
    }
}
