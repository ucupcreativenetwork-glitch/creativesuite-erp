<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cs_core_user_verification_otps');
        Schema::dropIfExists('cs_core_user_activation_tokens');

        Schema::create('cs_core_user_activation_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('cs_core_users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expired_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'used_at']);
        });

        Schema::create('cs_core_user_verification_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('cs_core_users')->cascadeOnDelete();
            $table->string('otp_code', 6);
            $table->string('session_token', 64)->nullable();
            $table->timestamp('expired_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'verified_at']);
            $table->index('session_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_core_user_verification_otps');
        Schema::dropIfExists('cs_core_user_activation_tokens');

        Schema::create('cs_core_user_activation_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_core_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('cs_core_users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expired_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'used_at']);
        });

        Schema::create('cs_core_user_verification_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_core_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('cs_core_users')->cascadeOnDelete();
            $table->string('otp_code', 6);
            $table->string('session_token', 64)->nullable();
            $table->timestamp('expired_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'verified_at']);
            $table->index('session_token');
        });
    }
};