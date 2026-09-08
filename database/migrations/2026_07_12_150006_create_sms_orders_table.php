<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sms_orders (blueprint Section 18.1). provider_cost and profit are PRIVATE
 * (hidden on the model). The schema note lists getatext/twilio, but the number
 * layer (Modules 6-11) also routes OTPs to 5sim / SMS-Activate / Telnyx by
 * country+type lane, so `provider` is a string rather than a narrow enum to
 * avoid blocking those lanes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('service_id')->nullable();
            $table->string('service_name')->nullable();
            $table->string('getatext_id')->nullable()->index();
            $table->string('phone_number')->nullable();
            $table->string('otp_code')->nullable();
            $table->enum('status', ['pending', 'waiting', 'completed', 'cancelled', 'timeout'])
                ->default('pending');
            $table->decimal('provider_cost', 12, 4)->default(0);
            $table->decimal('charged_to_user', 18, 4)->default(0);
            $table->decimal('profit', 12, 4)->default(0);
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_orders');
    }
};
