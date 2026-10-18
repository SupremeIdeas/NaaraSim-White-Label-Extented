<?php

namespace App\Jobs;

use App\Services\GiftCards\GiftCardLogoWizard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Logo Wizard as a queued job (it makes many outbound requests, so never inside a web request). Idempotent: approved logos are skipped on a re-run. */
class RunGiftCardLogoWizardJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3000;

    public function handle(GiftCardLogoWizard $wizard): void
    {
        $wizard->run();
    }
}
