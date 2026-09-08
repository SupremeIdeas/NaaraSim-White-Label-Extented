<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Generate a VAPID key pair for self-hosted web push (owner request). Run once,
 * then paste the two lines into .env. The private key is a secret — it is never
 * committed or shown to the browser.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate a VAPID key pair for web-push notifications';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('Add these to your .env (keep the PRIVATE key secret):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->comment('VAPID_SUBJECT defaults to APP_URL; set a mailto: or https URL if you prefer.');

        return self::SUCCESS;
    }
}
