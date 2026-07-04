<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_platform_subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->decimal('price_monthly', 18, 2);
            $table->decimal('price_yearly', 18, 2);
            $table->unsignedInteger('max_users');
            $table->unsignedInteger('max_branches');
            $table->unsignedInteger('max_storage_mb');
            $table->json('features');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cs_platform_tenants', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 36)->unique();
            $table->string('name', 200);
            $table->string('slug', 100)->unique();
            $table->enum('status', ['TRIAL', 'ACTIVE', 'SUSPENDED', 'CANCELLED'])->default('TRIAL');
            $table->foreignId('plan_id')->nullable()->constrained('cs_platform_subscription_plans')->nullOnDelete();
            $table->unsignedInteger('max_users')->default(10);
            $table->unsignedInteger('max_branches')->default(1);
            $table->unsignedInteger('max_storage_mb')->default(1024);
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->string('locale', 10)->default('id_ID');
            $table->json('settings')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_platform_tenants');
        Schema::dropIfExists('cs_platform_subscription_plans');
    }
};