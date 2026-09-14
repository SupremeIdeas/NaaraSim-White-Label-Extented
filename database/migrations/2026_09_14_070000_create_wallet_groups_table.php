<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared-wallet / family plan primitive (Prompt 11 §3). A WalletGroup's
     * spending always debits the OWNER's own UserWallet — never a pooled
     * balance of its own — so this table is purely who-can-spend-from-whose-
     * wallet bookkeeping, not a second money store.
     */
    public function up(): void
    {
        Schema::create('wallet_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_groups');
    }
};
