<?php

namespace App\Console\Commands;

use App\PayoutRails\Grey\GreyWebhookRegistrar;
use Illuminate\Console\Command;

class GreyRegisterWebhookCommand extends Command
{
    protected $signature = 'grey:register-webhook {--force : register again even if a webhook secret is already saved}';

    protected $description = 'Register this platform\'s payout webhook with Grey through its API and save the signing secret';

    public function handle(GreyWebhookRegistrar $registrar): int
    {
        $r = $registrar->register((bool) $this->option('force'));
        $r['ok'] ? $this->info($r['message']) : $this->error($r['message']);

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
