<?php

namespace App\Livewire;

use App\Models\GiftCardProduct;
use App\Services\GiftCards\GiftCardException;
use App\Services\GiftCards\GiftCardOrderService;
use App\Support\FeatureFlags;
use App\Support\GiftCardPricing;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Naara Gift storefront (Phase 2 — browse + brand detail + pricing). Behind the
 * `naara_gift` feature flag (off until the catalogue is synced and Phase-3
 * checkout is live). Shows the unified Reloadly/Zendit catalogue as brands, with
 * FIXED/RANGE denominations priced through PricingEngine — provider + cost never
 * exposed. The purchase money path is Phase 3.
 */
#[Layout('components.layouts.customer')]
class GiftCards extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?string $country = null;

    public ?int $selectedId = null;

    /** Chosen amount (FIXED face or RANGE value). */
    public $amount = null;

    /** Dynamic required-field values, keyed by field key. */
    public array $fields = [];

    /** Live once the API keys are in; otherwise the page shows Coming Soon. */
    public bool $live = false;

    public function booted(): void
    {
        // Admin kill-switch off → hide entirely. Admin on but no keys yet →
        // reachable, but renders a Coming-Soon state (flips live automatically
        // the moment the keys are saved — no manual editing).
        abort_unless(FeatureFlags::adminEnabled('naara_gift'), 404);
        // Batch 8: a white-label fork whose license tier locks gift cards has
        // the feature hidden entirely (a 404, like any disabled feature) until
        // the operator pays up. Inert on the master (never locked there).
        abort_if(\App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_GIFT_CARDS), 404);
        $this->live = FeatureFlags::configured('naara_gift');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function select(int $id): void
    {
        $product = GiftCardProduct::storefront()->findOrFail($id);
        $this->selectedId = $product->id;
        $this->amount = null;
        $this->fields = collect($product->required_fields ?? [])
            ->mapWithKeys(fn ($f) => [($f['key'] ?? 'field') => ''])->all();
    }

    public function close(): void
    {
        $this->selectedId = null;
        $this->amount = null;
        $this->fields = [];
    }

    /** Buy the selected gift card → the money path, then the receipt screen. */
    public function buy(GiftCardOrderService $orders)
    {
        abort_unless($this->live, 404); // no purchases in Coming-Soon mode
        $product = GiftCardProduct::storefront()->findOrFail($this->selectedId);
        try {
            $order = $orders->purchase(Auth::user(), $product, (float) $this->amount, array_map('strval', $this->fields));
        } catch (GiftCardException $e) {
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());

            return null;
        }

        return $this->redirect(route('gift-cards.order', $order), navigate: true);
    }

    public function render()
    {
        // Coming-Soon mode (no keys yet): skip the catalogue queries entirely.
        if (! $this->live) {
            return view('livewire.gift-cards', [
                'products' => null, 'countries' => collect(), 'selected' => null, 'denominations' => null,
            ]);
        }

        $products = GiftCardProduct::storefront()
            ->when($this->search !== '', fn ($q) => $q->where('brand_name', 'like', '%'.$this->search.'%'))
            ->when($this->country, fn ($q) => $q->where('country', $this->country))
            ->orderByDesc('featured')->orderBy('brand_name')
            ->paginate(18);

        $selected = $this->selectedId ? GiftCardProduct::storefront()->find($this->selectedId) : null;
        $denominations = $selected ? app(GiftCardPricing::class)->denominations($selected) : null;

        return view('livewire.gift-cards', [
            'products' => $products,
            'countries' => GiftCardProduct::storefront()->whereNotNull('country')->distinct()->orderBy('country')->pluck('country'),
            'selected' => $selected,
            'denominations' => $denominations,
        ]);
    }
}
