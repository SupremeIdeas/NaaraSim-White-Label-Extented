<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master-Only Distribution Lock — a structural restriction, not just an
 * unchecked toggle. `distribution_scope` is decided ONCE, at build time
 * (`update:package --master-only`), never adjustable later from a toggle:
 * - 'distributable' (default) — behaves exactly as today.
 * - 'master_only' — can NEVER be published to white label, enforced
 *   server-side in PackagePublisher (see App\Support\UpdateManifest).
 * Nullable-safe default keeps every existing row (built before this column
 * existed) exactly as distributable as it always was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributed_packages', function (Blueprint $table) {
            $table->string('distribution_scope')->default('distributable')->after('tier_requirement');
        });
    }

    public function down(): void
    {
        Schema::table('distributed_packages', function (Blueprint $table) {
            $table->dropColumn('distribution_scope');
        });
    }
};
