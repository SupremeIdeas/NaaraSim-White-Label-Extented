<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\AdminPath;
use Illuminate\Database\Seeder;

/**
 * NAARA-PROMPT-22 §2.1 — seeds the initial `admin_path` Setting row from
 * config('admin.path') so a fresh install's Admin -> Security dashboard shows
 * the correct current value immediately, not empty. Idempotent — an existing
 * value (e.g. already changed from the dashboard) is never overwritten.
 */
class AdminPathSeeder extends Seeder
{
    public function run(): void
    {
        if (Setting::query()->where('key', AdminPath::SETTING_KEY)->exists()) {
            return;
        }

        Setting::setValue(AdminPath::SETTING_KEY, config('admin.path'), 'security', 'Admin panel URL path (super_admin editable).');
    }
}
