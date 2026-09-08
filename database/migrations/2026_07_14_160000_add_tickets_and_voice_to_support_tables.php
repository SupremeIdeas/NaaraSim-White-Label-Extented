<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Human handoff + voice (Module 25). A support_conversation doubles as the
 * ticket: when the AI escalates, staff pick it up (assigned_to), work it
 * (status), and it carries a priority. support_messages gain a voice attachment
 * (path + render status) so both the AI and staff can reply with audio, and
 * users can send voice notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            $table->string('status')->default('open')->after('escalated'); // open|assigned|resolved|closed
            $table->foreignId('assigned_to')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->string('priority')->default('normal')->after('assigned_to'); // low|normal|high
            $table->timestamp('last_human_reply_at')->nullable()->after('escalated_at');
            $table->index(['status', 'escalated']);
        });

        Schema::table('support_messages', function (Blueprint $table) {
            // role already covers user|assistant|staff. Add a voice attachment.
            $table->string('voice_path')->nullable()->after('meta');
            $table->string('voice_status')->nullable()->after('voice_path'); // pending|ready|failed
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn(['voice_path', 'voice_status']);
        });
        Schema::table('support_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['status', 'priority', 'last_human_reply_at']);
        });
    }
};
