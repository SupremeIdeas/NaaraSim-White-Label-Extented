<?php

namespace App\Console\Commands;

use App\Services\Updater\PackageBuilder;
use Illuminate\Console\Command;

/**
 * Generate an Ed25519 keypair for signing update packages (blueprint Batch 1
 * §1.4). Run once on the publisher/CI machine. The PUBLIC half goes into every
 * instance's .env as NAARA_UPDATE_PUBLIC_KEY; the PRIVATE half is a secret that
 * signs packages and must never be committed or placed inside a deployed app.
 *
 * Mirrors the existing `webpush:vapid` keygen command's shape and warnings.
 */
class UpdateKeygenCommand extends Command
{
    protected $signature = 'update:keygen';

    protected $description = 'Generate an Ed25519 keypair for signing NaaraSim update packages';

    public function handle(): int
    {
        $keys = PackageBuilder::generateKeypair();

        $this->info('Update signing keypair (Ed25519, base64):');
        $this->newLine();
        $this->line('Add the PUBLIC key to every instance\'s .env (safe to ship):');
        $this->line('NAARA_UPDATE_PUBLIC_KEY='.$keys['public']);
        $this->newLine();
        $this->comment('Keep the PRIVATE key OUT of git and off deployed servers. Pass it to');
        $this->comment('`update:package --key=<file>` or a CI secret named NAARA_UPDATE_PRIVATE_KEY:');
        $this->line('NAARA_UPDATE_PRIVATE_KEY='.$keys['private']);
        $this->newLine();
        $this->warn('If this private key leaks, rotate it: regenerate, redistribute the new public key, and re-sign.');

        return self::SUCCESS;
    }
}
