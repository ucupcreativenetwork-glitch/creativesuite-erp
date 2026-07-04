<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_core_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('email');
            $table->string('password');
            $table->string('full_name', 200);
            $table->string('phone', 20)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->foreignId('default_company_id')->nullable()->constrained('cs_core_companies')->nullOnDelete();
            $table->foreignId('default_branch_id')->nullable()->constrained('cs_core_branches')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_platform_admin')->default(false);
            $table->boolean('mfa_enabled')->default(false);
            $table->text('mfa_secret')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('cs_core_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('action', 50);
            $table->string('code', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('cs_core_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('cs_core_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('cs_core_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('cs_core_permissions')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('cs_core_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('cs_core_users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('cs_core_roles')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('cs_core_branches')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'role_id', 'branch_id']);
        });

        Schema::create('cs_core_user_company_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('cs_core_users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_core_user_company_access');
        Schema::dropIfExists('cs_core_user_roles');
        Schema::dropIfExists('cs_core_role_permissions');
        Schema::dropIfExists('cs_core_roles');
        Schema::dropIfExists('cs_core_permissions');
        Schema::dropIfExists('cs_core_users');
    }
};