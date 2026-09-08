<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic reactions (component-library batch 2 §5). One row per user per
 * reactable subject (a blog post today; any model tomorrow). A user has at most
 * ONE active reaction on a subject — changing it updates the row, removing it
 * deletes the row — enforced by the unique index. `type` is a short reaction
 * key (like/cheer/celebrate/appreciate/smile), never user free-text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('reactable'); // reactable_type + reactable_id (indexed)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->timestamp('created_at')->nullable();

            $table->unique(['reactable_type', 'reactable_id', 'user_id'], 'reactions_unique_per_user');
            $table->index(['reactable_type', 'reactable_id', 'type'], 'reactions_count_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
