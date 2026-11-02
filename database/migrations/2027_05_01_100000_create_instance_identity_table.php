<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row that says WHICH install owns this database. The installer and the doctor compare it with INSTANCE_ID in .env, so two installs can never silently share one database. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('instance_identity')) {
            return;
        }
        Schema::create('instance_identity', function (Blueprint $t) {
            $t->id();
            $t->string('instance_id', 32)->unique('instance_identity_instance_id_unique');
            $t->string('edition', 24)->default('whitelabel');
            $t->string('app_key_hash', 64);
            $t->string('db_fingerprint', 64);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instance_identity');
    }
};
