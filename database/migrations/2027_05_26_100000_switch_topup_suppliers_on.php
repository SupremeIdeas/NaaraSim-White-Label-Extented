<?php

use App\Models\TopupProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Every top-up supplier starts switched on (routing still skips one that has no key or no float); an admin can switch any off. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('topup_providers')) {
            TopupProvider::query()->where('enabled', false)->update(['enabled' => true]);
        }
    }

    public function down(): void {}
};
