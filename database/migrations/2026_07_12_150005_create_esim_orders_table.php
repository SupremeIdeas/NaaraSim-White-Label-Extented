<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * esim_orders (blueprint Section 18.1). wholesale_cost is stored per order for
 * profit tracking but is PRIVATE (hidden on the model). Indexed on
 * user_id+status and created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esim_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('esim_plans')->nullOnDelete();
            $table->string('provider');
            $table->string('provider_order_ref')->nullable()->index();
            $table->string('iccid')->nullable()->index();
            $table->string('qr_code_url')->nullable();
            $table->enum('status', ['pending', 'processing', 'active', 'expired', 'failed', 'cancelled'])
                ->default('pending');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('data_remaining_mb')->nullable();
            $table->decimal('price_charged', 18, 4)->default(0);
            $table->decimal('wholesale_cost', 12, 4)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esim_orders');
    }
};
