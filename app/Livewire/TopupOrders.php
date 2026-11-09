<?php

namespace App\Livewire;

use App\Models\TopupOrder;
use App\Support\ComingSoon;
use App\Support\FeatureEntitlements;
use App\Support\FeatureLocks;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** "My top-ups": the list and a single receipt/status page (polls while the supplier is still working). No supplier name, reference or cost is ever rendered. */
#[Layout('components.layouts.customer')]
class TopupOrders extends Component
{
    use WithPagination;

    public ?string $uuid = null;

    public function mount(?string $uuid = null): void
    {
        ComingSoon::abortIfOff('naara_topup');
        abort_if(FeatureEntitlements::locked(FeatureLocks::F_TOPUP), 404);
        $this->uuid = $uuid;
    }

    /** Customer-facing words for the internal state machine. */
    public static function label(string $status): array
    {
        return match ($status) {
            TopupOrder::DELIVERED => ['Delivered', 'best'],
            TopupOrder::REFUNDED, TopupOrder::FAILED => ['Refunded', 'out'],
            TopupOrder::PAYMENT_FAILED => ['Not paid', 'out'],
            default => ['Sending', 'gold'],
        };
    }

    public function render()
    {
        if ($this->uuid) {
            $order = TopupOrder::where('user_id', Auth::id())->where('uuid', $this->uuid)->firstOrFail();

            return view('livewire.topup-order', ['order' => $order, 'settled' => $order->isTerminal() || $order->status === TopupOrder::FAILED]);
        }

        return view('livewire.topup-orders', ['orders' => TopupOrder::where('user_id', Auth::id())->latest('id')->paginate(15)]);
    }
}
