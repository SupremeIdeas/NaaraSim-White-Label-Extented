<?php

namespace Database\Seeders;

use App\Services\GiftCards\GiftCardLogoWizard;
use Illuminate\Database\Seeder;

/** Loads the shipped gift-card logo pack so every brand has a real logo before the first sync (no network needed). */
class GiftCardLogoSeeder extends Seeder
{
    public function run(): void
    {
        $n = app(GiftCardLogoWizard::class)->seedFromPack();
        $this->command?->info("Gift-card logo pack: {$n} brands seeded.");
        app(GiftCardLogoWizard::class)->applyToProducts();
    }
}
