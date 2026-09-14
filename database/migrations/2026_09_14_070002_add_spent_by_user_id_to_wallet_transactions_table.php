<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prompt 11 §3: "extend WalletTransaction with a nullable spent_by_user_id
     * distinct from the wallet-owning user_id, don't repurpose an existing
     * column." Null for every normal, non-group transaction (the existing
     * `user_id` already identifies who spent AND whose wallet it was) — only
     * a group-plan purchase sets this to the member who spent while `user_id`
     * stays the group owner whose wallet was actually debited.
     */
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('spent_by_user_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('spent_by_user_id');
        });
    }
};
