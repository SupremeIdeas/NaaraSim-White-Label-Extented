<?php

namespace App\Services\Payouts\Hardening;

use App\Models\PayoutRequest;
use App\Services\Payouts\LookupResult;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\SupportsStatusLookup;
use App\Support\Auditor;
use Throwable;

/**
 * After ANY database restore/rollback the platform's idea of "what was sent" can be older than
 * what the providers really did (Addendum D-3.9). This asks every provider, by our reference,
 * about each request that is not final AND each one finalised since the backup was taken, and
 * reports where the books and the provider disagree. With $apply it feeds the provider's answer
 * through the normal idempotent confirm()/fail() — never any other path, never a re-send.
 */
class PostRestoreCheck
{
    public function __construct(private readonly PayoutService $payouts) {}

    /**
     * @return array{checked:int, mismatches:list<array<string,mixed>>, unverifiable:list<int>, applied:int}
     */
    public function run(?\DateTimeInterface $since, bool $apply = false): array
    {
        $out = ['checked' => 0, 'mismatches' => [], 'unverifiable' => [], 'applied' => 0];

        $query = PayoutRequest::query()
            ->whereNotNull('provider')
            ->where(function ($q) use ($since) {
                $q->whereIn('status', [PayoutRequest::PROCESSING, PayoutRequest::APPROVED, PayoutRequest::AWAITING_FUNDS, PayoutRequest::PENDING]);
                if ($since !== null) {
                    $q->orWhere('updated_at', '>=', $since);
                }
            })
            ->orderBy('id');

        $query->chunkById(200, function ($rows) use (&$out, $apply) {
            foreach ($rows as $request) {
                $gateway = $this->payouts->gatewayFor($request->provider);
                if (! $gateway instanceof SupportsStatusLookup) {
                    $out['unverifiable'][] = $request->id;

                    continue;
                }
                $out['checked']++;

                try {
                    $res = $gateway->lookupTransfer($request);
                } catch (Throwable $e) {
                    $out['unverifiable'][] = $request->id;

                    continue;
                }

                $expect = $this->expected($request, $res);
                if ($expect === null) {
                    continue;
                }
                $out['mismatches'][] = ['request_id' => $request->id, 'provider' => $request->provider, 'our_status' => $request->status, 'provider_says' => $res->status ?? $res->state, 'action' => $expect];

                if ($apply) {
                    if ($expect === 'confirm') {
                        $this->payouts->confirm($request, $res->providerRef);
                    } elseif ($expect === 'fail') {
                        $this->payouts->fail($request, 'Post-restore check: provider reported '.($res->status ?? 'failed'));
                    }
                    $out['applied']++;
                    Auditor::log('payout.post_restore_applied', 'PayoutRequest', $request->id, ['action' => $expect]);
                }
            }
        });

        return $out;
    }

    /** What would have to change for the books to match the provider (null = they already agree). */
    private function expected(PayoutRequest $request, LookupResult $res): ?string
    {
        if ($res->state === LookupResult::FOUND) {
            return match (true) {
                $res->status === 'paid' && $request->status !== PayoutRequest::PAID => 'confirm',
                $res->status === 'failed' && ! in_array($request->status, [PayoutRequest::FAILED, PayoutRequest::REVERSED], true) => 'fail',
                // Provider moved money but our record says it never left: the dangerous restore case.
                in_array($res->status, ['processing'], true) && in_array($request->status, [PayoutRequest::PENDING, PayoutRequest::APPROVED, PayoutRequest::AWAITING_FUNDS], true) => 'review',
                default => null,
            };
        }
        if ($res->state === LookupResult::NOT_FOUND && $request->status === PayoutRequest::PAID) {
            return 'review'; // we say paid, the provider has no record
        }

        return null;
    }
}
