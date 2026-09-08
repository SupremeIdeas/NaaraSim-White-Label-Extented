<?php

namespace Database\Seeders;

use App\Models\NumbersBentoCard;
use App\Support\NumbersBento;
use Illuminate\Database\Seeder;

/**
 * Seeds the six Numbers bento cards from the code defaults so they exist as
 * editable rows in the admin from day one. Idempotent (updateOrCreate on key);
 * only fills the persisted content fields — the layout/link stay in code.
 */
class NumbersBentoSeeder extends Seeder
{
    public function run(): void
    {
        $order = array_flip(NumbersBento::ORDER);

        foreach (NumbersBento::defaults() as $key => $def) {
            NumbersBentoCard::updateOrCreate(['key' => $key], [
                'badge_label' => $def['badge_label'],
                'title' => $def['title'],
                'subtitle' => $def['subtitle'],
                'bullets' => $def['bullets'],
                'sort_order' => $order[$key] ?? 0,
                // Preserve an admin's uploaded image on re-seed (only set if empty).
                'is_active' => true,
            ]);
        }

        NumbersBento::flush();
    }
}
