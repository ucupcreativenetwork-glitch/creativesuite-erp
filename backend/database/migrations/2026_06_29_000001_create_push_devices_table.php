<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_core_push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('cs_core_users')->cascadeOnDelete();
            $table->string('expo_push_token', 255);
            $table->string('platform', 20)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('expo_push_token');
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_core_push_devices');
    }
};