<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ROADMAP §Layer 3 — merchant eligibility. A user unlocks the merchant
 * programme by meeting ONE of: a minimum lifetime spend, a paid one-time
 * "fast-route" enrollment, or a minimum number of referred users. This stamps
 * when they paid the fast-route fee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('merchant_enrollment_paid_at')->nullable()->after('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('merchant_enrollment_paid_at'));
    }
};
