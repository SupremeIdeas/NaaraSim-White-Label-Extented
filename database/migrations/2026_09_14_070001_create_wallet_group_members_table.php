<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prompt 11 §3. A row exists once the owner invites someone; `accepted_at`
     * null means a pending invite (not yet allowed to spend). Spend caps are
     * nullable per-currency = uncapped, exactly as specified — no cap column
     * is repurposed or shared across currencies.
     */
    public function up(): void
    {
        Schema::create('wallet_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('spend_cap_usd', 12, 4)->nullable();
            $table->decimal('spend_cap_ngn', 14, 4)->nullable();
            $table->timestamp('invited_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['wallet_group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_group_members');
    }
};
