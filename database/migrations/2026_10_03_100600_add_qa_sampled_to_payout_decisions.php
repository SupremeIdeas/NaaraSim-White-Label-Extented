<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_decisions', function (Blueprint $table) {
            // 2–5% of auto-approved payouts are flagged for post-hoc admin review (does not delay sending).
            $table->boolean('qa_sampled')->default(false)->after('shadow');
            $table->string('reason', 64)->nullable()->after('decision'); // machine code of the deciding rule
        });
    }

    public function down(): void
    {
        Schema::table('payout_decisions', function (Blueprint $table) {
            $table->dropColumn(['qa_sampled', 'reason']);
        });
    }
};
