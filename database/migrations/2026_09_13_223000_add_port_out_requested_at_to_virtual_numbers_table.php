<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Port-out / right-to-leave (Prompt 11): records when a customer asks to take
 * their Naara Line to another carrier, so we can surface the request state and
 * ops can facilitate the handoff. We never obstruct a leaving customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_numbers', function (Blueprint $table) {
            $table->timestamp('port_out_requested_at')->nullable()->after('renewal_notice_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_numbers', function (Blueprint $table) {
            $table->dropColumn('port_out_requested_at');
        });
    }
};
