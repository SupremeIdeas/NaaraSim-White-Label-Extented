<?php

namespace App\Console\Commands;

use App\Jobs\AlertAdminJob;
use App\Models\WhiteLabelProjectIntake;
use App\Services\Updater\WhiteLabelProjectIntakeService;
use Illuminate\Console\Command;

/**
 * Prompt 21-EXT2 §6 — daily sweep that flips an `in_progress` project intake
 * to `completed` once its admin-set deploy timeline has elapsed, and emails
 * the merchant. One failing row must never abort the rest of the sweep, same
 * isolation discipline as every other batch command in this codebase.
 */
class CheckWhiteLabelDeployTimelinesCommand extends Command
{
    protected $signature = 'whitelabel:intake-deploy-check';

    protected $description = 'Complete white-label project intakes whose deploy timeline has elapsed, and notify the merchant';

    public function handle(WhiteLabelProjectIntakeService $intakes): int
    {
        $completed = 0;

        WhiteLabelProjectIntake::where('status', WhiteLabelProjectIntake::STATUS_IN_PROGRESS)
            ->chunkById(100, function ($chunk) use ($intakes, &$completed) {
                foreach ($chunk as $intake) {
                    try {
                        if ($intakes->completeIfElapsed($intake)) {
                            $completed++;
                        }
                    } catch (\Throwable $e) {
                        AlertAdminJob::dispatch(
                            code: 'white_label_deploy_check_failed',
                            message: "Deploy-timeline check failed for intake {$intake->id}: {$e->getMessage()}",
                            context: ['intake_id' => $intake->id, 'exception' => $e::class],
                        );
                    }
                }
            });

        $this->info("Completed {$completed} white-label deployment(s).");

        return self::SUCCESS;
    }
}
