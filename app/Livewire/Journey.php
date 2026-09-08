<?php

namespace App\Livewire;

use App\Models\CreditLedger;
use App\Models\EsimOrder;
use App\Models\JourneyGoal;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Journey\JourneyGoalService;
use App\Support\CreditSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "My Journey" — three real, data-grounded panels, no invented metrics:
 *  - Loyalty Milestones: the user's own NaaraCredits earning history
 *    (CreditLedger), grouped into the real mechanisms the app actually grants
 *    credits through (signup, first purchase, daily check-in, ads, other).
 *  - Travel/eSIM Journey: the user's own eSIM purchase history as a
 *    passport-stamp timeline (country, plan, validity) — no usage-over-time
 *    graph, since that requires a snapshot pipeline this platform doesn't
 *    have yet (getUsage() is polled nowhere).
 *  - Goals & Challenges: admin-defined achievements (JourneyGoalService) —
 *    evaluated lazily here as a fallback (event-driven dispatch elsewhere
 *    makes the reward feel instant; this guarantees it's never missed).
 */
#[Layout('components.layouts.customer')]
class Journey extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'milestones';

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['milestones', 'travel', 'goals'], true) ? $tab : 'milestones';
    }

    /** @return array<int, array{key: string, icon: string, title: string, description: string, status: string, meta: ?string}> */
    private function milestones(User $user): array
    {
        $ledger = CreditLedger::where('user_id', $user->id)->where('type', 'earn')->get();
        $bySource = fn (string $source) => $ledger->where('source', $source);

        $signup = $bySource('signup')->first();
        $firstPurchase = $bySource('first_purchase')->first();
        $checkins = $bySource('checkin');
        $ads = $bySource('ad_reward');

        // Everything that isn't one of the named mechanisms above (social
        // follows, brand videos, admin/staff goodwill, referral rewards) —
        // grouped rather than invented per-type, since new sources can be
        // added over time without this page needing to know every one.
        $known = ['signup', 'first_purchase', 'checkin', 'ad_reward'];
        $other = $ledger->reject(fn ($row) => in_array($row->source, $known, true));

        $items = [
            [
                'key' => 'signup', 'icon' => 'star', 'title' => 'Welcome bonus',
                'description' => 'Joining NaaraSim.',
                'status' => $signup ? 'done' : 'locked',
                'meta' => $signup ? '+'.number_format((float) $signup->amount, 0).' credits · '.$signup->created_at->format('M j, Y') : null,
            ],
            [
                'key' => 'first_purchase', 'icon' => 'sim', 'title' => 'First purchase',
                'description' => 'Buy your first eSIM or number.',
                'status' => $firstPurchase ? 'done' : 'locked',
                'meta' => $firstPurchase ? '+'.number_format((float) $firstPurchase->amount, 0).' credits · '.$firstPurchase->created_at->format('M j, Y') : 'Not yet — head to the catalogue.',
            ],
            [
                'key' => 'checkin', 'icon' => 'zap', 'title' => 'Daily check-in streak',
                'description' => $checkins->isEmpty() ? 'Check in daily from Rewards to start earning.' : 'Keep checking in daily to grow your streak.',
                'status' => $checkins->isEmpty() ? 'locked' : 'ongoing',
                'meta' => $checkins->isEmpty() ? null : app(JourneyGoalService::class)->checkinStreak($user).'-day streak · '.$checkins->count().' total check-ins',
            ],
        ];

        if (CreditSettings::adsActive() || $ads->isNotEmpty()) {
            $items[] = [
                'key' => 'ads', 'icon' => 'play', 'title' => 'Watch & earn',
                'description' => 'Earn credits from Rewards by watching rewarded ads.',
                'status' => $ads->isEmpty() ? 'locked' : 'ongoing',
                'meta' => $ads->isEmpty() ? null : $ads->count().' watched · +'.number_format((float) $ads->sum('amount'), 0).' credits total',
            ];
        }

        if ($other->isNotEmpty()) {
            $items[] = [
                'key' => 'other', 'icon' => 'gift', 'title' => 'Other rewards',
                'description' => 'Social follows, brand videos, and other bonuses.',
                'status' => 'ongoing',
                'meta' => $other->count().' reward'.($other->count() === 1 ? '' : 's').' · +'.number_format((float) $other->sum('amount'), 0).' credits total',
            ];
        }

        return $items;
    }

    /** @return array<int, array{goal: JourneyGoal, progress: array}> */
    private function goals(User $user, JourneyGoalService $engine): array
    {
        // Lazy fallback evaluation: event-driven dispatch (checkout, number
        // purchase, check-in, etc.) already grants the moment an action
        // completes it, but a visit here always guarantees it's never missed
        // (e.g. a goal an admin only just activated).
        $newlyGranted = $engine->evaluate($user);
        foreach ($newlyGranted as $claim) {
            $this->dispatch('nx-toast', variant: 'hero', type: 'success', title: 'Goal reached!',
                message: '+'.number_format((float) $claim->credits_granted, 0)." NaaraCredits — {$claim->goal->title}");
        }

        return $engine->goalsFor($user)
            ->map(fn ($goal) => ['goal' => $goal, 'progress' => $engine->progress($user, $goal)])
            ->all();
    }

    public function render(CreditService $credits, JourneyGoalService $goalEngine)
    {
        $user = Auth::user();

        return view('livewire.journey', [
            'creditsEnabled' => CreditSettings::enabled(),
            'milestones' => CreditSettings::enabled() ? $this->milestones($user) : [],
            'orders' => EsimOrder::where('user_id', $user->id)->with('plan')
                ->orderByDesc('created_at')->get(),
            // Only evaluate/query goals when that tab is actually open — the
            // event-driven dispatch (checkout, number purchase, etc.) is the
            // primary path, this is just the guaranteed-to-catch-up fallback.
            'goals' => ($this->tab === 'goals' && CreditSettings::enabled()) ? $this->goals($user, $goalEngine) : [],
        ]);
    }
}
