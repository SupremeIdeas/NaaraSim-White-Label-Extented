<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-account appearance (Prompt 20 §17 + Prompt 21 §4). Presentation only: no provider, wallet, order or billing
 * table is touched, and nothing is added to `users`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appearance_presets', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 8);                       // skin | accent
            $table->string('key', 24);
            $table->string('label', 40);
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->smallInteger('sort')->default(0);
            // Access layer (Prompt 21). Nothing reads these until the access batches ship; the columns exist so a skin is
            // never exposed as free and locked later.
            $table->string('access', 8)->default('pro');     // free | pro
            $table->boolean('trial_enabled')->default(true);
            $table->smallInteger('trial_minutes')->nullable();
            $table->unsignedTinyInteger('min_plan_tier')->nullable();
            $table->boolean('unlockable_by_goal')->default(true);
            $table->dateTime('free_from')->nullable();
            $table->dateTime('free_until')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'key']);
        });

        Schema::create('user_appearance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('skin_key', 24)->nullable();
            $table->string('accent_key', 24)->nullable();    // preset key or 'custom'
            $table->char('accent_hex', 7)->nullable();       // #rrggbb lower-case, when accent_key = custom
            $table->string('mode', 8)->nullable();           // light | dark | system
            foreach (['round', 'dens', 'ts', 'depth', 'font', 'motion'] as $dial) {
                $table->string($dial, 12)->nullable();
            }
            $table->string('last_free_skin_key', 24)->nullable();
            $table->string('last_pro_skin_key', 24)->nullable();
            $table->string('last_pro_accent_key', 24)->nullable();
            $table->timestamps();
        });

        // Upgraded installs get the rows without re-running the whole seeder (idempotent).
        (new \Database\Seeders\AppearancePresetSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_appearance');
        Schema::dropIfExists('appearance_presets');
    }
};
