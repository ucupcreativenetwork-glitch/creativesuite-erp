<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_core_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('legal_name', 300);
            $table->string('trade_name', 300)->nullable();
            $table->enum('entity_type', ['PT', 'CV', 'UD', 'KOPERASI', 'PERORANGAN'])->default('PT');
            $table->string('npwp', 20)->nullable();
            $table->string('nitku', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->boolean('is_pkp')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'npwp']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('cs_core_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_head_office')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'company_id', 'code']);
            $table->index(['tenant_id', 'company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_core_branches');
        Schema::dropIfExists('cs_core_companies');
    }
};