<?php

namespace App\Console\Commands;

use App\Services\GiftCards\GiftCardLogoWizard;
use Illuminate\Console\Command;

class GiftCardsLogosCommand extends Command
{
    protected $signature = 'giftcards:logos {--limit= : stop after N brands} {--refresh : redo brands that already have an approved logo}';

    protected $description = 'Logo Wizard: fetch, normalise and store a real logo for every gift-card brand (provider image, Simple Icons, official site, monogram fallback)';

    public function handle(GiftCardLogoWizard $wizard): int
    {
        $stats = $wizard->run($this->option('limit') !== null ? (int) $this->option('limit') : null, (bool) $this->option('refresh'), function ($row) {
            $this->line(sprintf('%-9s %-11s %s', $row->status, $row->source, $row->brand_name));
        });
        $this->info('brands '.$stats['brands'].' | approved '.$stats['approved'].' | needs review '.$stats['review'].' | monogram fallback '.$stats['fallback'].' | already approved '.$stats['skipped']);

        return self::SUCCESS;
    }
}
