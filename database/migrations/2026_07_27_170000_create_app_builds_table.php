<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Native app build history (App Export prompt §1). One row per "Generate Build"
 * request. The compile itself runs on a CI runner (Android) or a cloud macOS
 * service (iOS) and reports back via a signed webhook, which flips status +
 * attaches the artifact URL and logs. Admin sees real state end to end.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_builds', function (Blueprint $table) {
            $table->id();
            $table->string('platform');                 // android | ios
            $table->string('artifact_type');            // apk | aab | ipa
            $table->string('version');                  // e.g. 1.2.0
            $table->unsignedInteger('build_number');
            $table->string('status')->default('queued'); // queued|building|ready|failed
            $table->string('artifact_url')->nullable();  // downloadable build output
            $table->text('release_notes')->nullable();
            $table->longText('log')->nullable();         // CI build log (surfaced for debugging)
            $table->string('external_ref')->nullable();  // provider build id (for polling)
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_builds');
    }
};
