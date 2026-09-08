<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner program (new). A partner shares in PLATFORM-WIDE profit (not their own
 * referred sales) at an admin-set percentage. Deliberately structured like a
 * Merchant (owner, status lifecycle) but with different economics and NO
 * conditional withdrawal gates — see PartnerEarning + PartnerPayoutService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');        // pending | active | suspended
            // Admin-set, admin-CONFIDENTIAL — never shown to the partner.
            $table->decimal('profit_share_pct', 6, 3)->default(0);
            $table->string('payout_cadence')->default('monthly'); // weekly | monthly
            $table->string('payout_mode')->default('manual');     // manual (admin-approved) | auto
            // The end date of the last period already paid, so a run never double-pays.
            $table->date('last_period_end')->nullable();
            $table->text('reason')->nullable();                   // suspension note
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique('owner_user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
