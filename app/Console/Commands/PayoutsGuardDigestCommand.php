<?php

namespace App\Console\Commands;

use App\Jobs\AlertAdminJob;
use App\Services\Payouts\Guardian\GuardianMetrics;
use Illuminate\Console\Command;

class PayoutsGuardDigestCommand extends Command
{
    protected $signature = 'payouts:guard-digest';

    protected $description = 'Daily summary of Payout Guardian activity for admins (sent only when there is something to say)';

    public function handle(GuardianMetrics $metrics): int
    {
        $m = $metrics->roll();
        $acted = array_sum($m['acted_24h']);
        $advisory = array_sum($m['advisory_24h']);

        if ($acted + $advisory + $m['manual_review'] + $m['deferred'] === 0) {
            return self::SUCCESS;
        }

        $parts = [
            "{$acted} decisions acted on, {$advisory} advisory (shadow)",
            "{$m['manual_review']} waiting for your review",
            "{$m['deferred']} deferred (cooling-off / float)",
        ];
        if ($m['qa_sampled_24h'] > 0) {
            $parts[] = "{$m['qa_sampled_24h']} auto-approved payouts flagged for QA review";
        }
        if ($m['breaker_active']) {
            $parts[] = 'a circuit breaker is ACTIVE';
        }

        AlertAdminJob::dispatch(code: 'guardian_digest', message: 'Payout Guardian, last 24h: '.implode('; ', $parts).'.', context: $m);
        $this->info('digest sent');

        return self::SUCCESS;
    }
}
