<?php

namespace App\Livewire;

use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandPartnerVideo;
use App\Models\BrandSubscriptionPlan;
use App\Models\SocialFollowClaim;
use App\Services\Brands\BrandSubscriptionService;
use App\Support\BrandCategories;
use App\Support\BrandHandleFormat;
use App\Support\MediaStorage;
use App\Support\SocialPlatforms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Business-facing brand management + guided onboarding (BUILD-9 §5.1/§5.5). A
 * pending listing shows a "complete your setup" mode; once every required field
 * is present it activates. Handles/videos are capped to the current plan; the
 * one-time-claim/follow history is preserved (handles are added/removed
 * individually, never bulk-replaced). Change plan or cancel from here.
 */
#[Layout('components.layouts.customer')]
class BrandManage extends Component
{
    use WithFileUploads;

    public array $profile = ['brand_name' => '', 'category' => '', 'short_description' => '', 'background_color' => '#0A6E6E'];

    public $heroImage = null;

    /** New-handle form. */
    public array $newHandle = ['platform' => 'instagram', 'handle_label' => '', 'handle_url' => ''];

    /** New-video form. */
    public array $newVideo = ['platform' => 'youtube', 'video_url' => ''];

    public ?string $flash = null;

    public ?string $error = null;

    private BrandPartner $brand;

    public function mount(BrandSubscriptionService $subs)
    {
        $brand = BrandPartner::where('owner_user_id', Auth::id())->first();
        if (! $brand) {
            return redirect()->route('brand.get-listed');
        }
        $this->brand = $brand;
        $this->profile = [
            'brand_name' => $brand->brand_name,
            'category' => $brand->category ?: '',
            'short_description' => $brand->short_description ?: '',
            'background_color' => $brand->background_color ?: '#0A6E6E',
        ];
    }

    private function brand(): BrandPartner
    {
        return BrandPartner::with(['plan', 'subscription', 'handles', 'videos'])
            ->where('owner_user_id', Auth::id())->firstOrFail();
    }

    public function saveProfile(BrandSubscriptionService $subs): void
    {
        $data = $this->validate([
            'profile.brand_name' => 'required|string|max:120',
            'profile.category' => 'required|string|max:80',
            'profile.short_description' => 'nullable|string|max:500',
            'profile.background_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'heroImage' => 'nullable|image|mimes:webp,jpg,jpeg,png|max:2048',
        ])['profile'];

        abort_unless(BrandCategories::isValid($data['category']), 422);
        $brand = $this->brand();
        if ($this->heroImage) {
            $brand->hero_image_path = MediaStorage::storePublic($this->heroImage, 'brand-hero');
            $this->heroImage = null;
        }
        $brand->fill([
            'brand_name' => $data['brand_name'],
            'category' => $data['category'],
            'short_description' => $data['short_description'],
            'background_color' => $data['background_color'],
        ])->save();

        $activated = $subs->activateIfComplete($brand);
        $this->flash = $activated ? 'Your listing is now live in the directory!' : 'Saved.';
    }

    public function addHandle(): void
    {
        $this->error = null;
        $brand = $this->brand();
        $limit = (int) ($brand->plan->handles_included ?? 1);
        if ($brand->handles->count() >= $limit) {
            $this->error = "Your plan allows {$limit} ".\Illuminate\Support\Str::plural('handle', $limit).'. Upgrade to add more.';

            return;
        }
        $this->validate([
            'newHandle.platform' => 'required|in:'.implode(',', SocialPlatforms::keys()),
            'newHandle.handle_label' => 'required|string|max:80',
            'newHandle.handle_url' => 'required|url|max:512',
        ]);
        if (! BrandHandleFormat::matches($this->newHandle['platform'], $this->newHandle['handle_url'])) {
            $this->addError('newHandle.handle_url', 'That doesn’t look like a valid '.SocialPlatforms::label($this->newHandle['platform']).' URL. '.BrandHandleFormat::hint($this->newHandle['platform']));

            return;
        }
        BrandPartnerHandle::create([
            'brand_partner_id' => $brand->id,
            'platform' => $this->newHandle['platform'],
            'handle_label' => $this->newHandle['handle_label'],
            'handle_url' => $this->newHandle['handle_url'],
            'credit_reward' => 2, // default surprise reward; admin can tune
            'verification' => 'self',
            'is_active' => true,
            'sort_order' => (int) $brand->handles()->max('sort_order') + 1,
        ]);
        $this->newHandle = ['platform' => 'instagram', 'handle_label' => '', 'handle_url' => ''];
        $this->flash = 'Handle added.';
    }

    public function removeHandle(int $id): void
    {
        BrandPartnerHandle::where('brand_partner_id', $this->brand()->id)->whereKey($id)->delete();
    }

    public function addVideo(): void
    {
        $this->error = null;
        $brand = $this->brand();
        $limit = (int) ($brand->plan->video_previews_allowed ?? 0);
        if ($limit <= 0) {
            $this->error = 'Your plan doesn’t include video previews.';

            return;
        }
        if ($brand->videos->count() >= $limit) {
            $this->error = "Your plan allows {$limit} video ".\Illuminate\Support\Str::plural('preview', $limit).'.';

            return;
        }
        $this->validate([
            'newVideo.platform' => 'required|in:youtube,vimeo',
            'newVideo.video_url' => 'required|url|max:512',
        ]);
        BrandPartnerVideo::create([
            'brand_partner_id' => $brand->id,
            'video_url' => $this->newVideo['video_url'],
            'platform' => $this->newVideo['platform'],
            'sort_order' => (int) $brand->videos()->max('sort_order') + 1,
        ]);
        $this->newVideo = ['platform' => 'youtube', 'video_url' => ''];
        $this->flash = 'Video added.';
    }

    public function removeVideo(int $id): void
    {
        BrandPartnerVideo::where('brand_partner_id', $this->brand()->id)->whereKey($id)->delete();
    }

    public function changePlan(int $planId, BrandSubscriptionService $subs)
    {
        $this->error = null;
        $plan = BrandSubscriptionPlan::active()->find($planId);
        $brand = $this->brand();
        if (! $plan) {
            return;
        }
        // §5.4: never silently drop content on a downgrade — ask them to trim first.
        if ($brand->handles->count() > $plan->handles_included) {
            $this->error = "That plan allows {$plan->handles_included} handles — remove ".($brand->handles->count() - $plan->handles_included).' first, then switch.';

            return null;
        }
        if ($brand->videos->count() > $plan->video_previews_allowed) {
            $this->error = "That plan allows {$plan->video_previews_allowed} videos — remove some first, then switch.";

            return null;
        }
        try {
            $subs->subscribe(Auth::user(), $plan);
        } catch (\App\Exceptions\InsufficientBalanceException) {
            $this->error = 'Top up your wallet to switch plans (first month of the new plan is charged now).';

            return null;
        }
        $this->flash = 'Plan changed to '.$plan->name.'.';

        return $this->redirect(route('brand.manage'), navigate: true);
    }

    public function cancel(BrandSubscriptionService $subs): void
    {
        $subs->cancel($this->brand());
        $this->flash = 'Your listing has been cancelled. You can resubscribe anytime.';
    }

    public function render()
    {
        $brand = $this->brand();
        $sub = $brand->subscription;
        $guarantee = (int) ($brand->plan->guaranteed_followers_per_handle_per_month ?? 0);
        $cycleStart = $sub ? Carbon::parse($sub->last_charged_at ?? $sub->started_at) : now()->startOfMonth();

        // Real delivery this cycle, per handle (the transparency Frank wanted).
        $delivery = $brand->handles->map(function ($h) use ($cycleStart, $guarantee) {
            $actual = SocialFollowClaim::where('brand_partner_handle_id', $h->id)
                ->where('claimed_at', '>=', $cycleStart)->count();

            return ['handle' => $h, 'actual' => $actual, 'guaranteed' => $guarantee];
        });

        return view('livewire.brand-manage', [
            'brand' => $brand,
            'subscription' => $sub,
            'plans' => BrandSubscriptionPlan::active()->ordered()->get(),
            'platforms' => SocialPlatforms::all(),
            'categories' => BrandCategories::all(),
            'delivery' => $delivery,
            'setupComplete' => app(BrandSubscriptionService::class)->setupComplete($brand),
        ]);
    }
}
