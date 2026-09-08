<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public status page (Section Builder prompt §1). Incidents + their update
 * timeline, and email/webhook subscribers. Component health itself is derived
 * live from the existing ProviderStatus infra (no second status source) — these
 * tables only carry the human-posted incident narrative + subscriptions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('component')->nullable();     // one of StatusPage::components() keys, or null = platform-wide
            $table->string('impact')->default('minor');   // minor | major | critical | maintenance
            $table->string('status')->default('investigating'); // investigating|identified|monitoring|resolved
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });

        Schema::create('incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('status_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('token', 64)->unique();       // unsubscribe token
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_updates');
        Schema::dropIfExists('status_subscribers');
        Schema::dropIfExists('incidents');
    }
};
