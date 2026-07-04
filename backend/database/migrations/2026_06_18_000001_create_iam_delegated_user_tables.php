<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_core_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->foreignId('parent_department_id')->nullable()->constrained('cs_core_departments')->nullOnDelete();
            $table->foreignId('head_user_id')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'company_id', 'code']);
        });

        Schema::create('cs_core_approval_workflow_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('name', 100);
            $table->string('module', 50)->default('USER_CREATION');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cs_core_approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_config_id')->constrained('cs_core_approval_workflow_configs')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('approver_role_code', 50);
            $table->boolean('can_override')->default(false);
            $table->unsignedInteger('sla_hours')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['workflow_config_id', 'step_order']);
        });

        Schema::create('cs_core_department_role_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('cs_core_departments')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('cs_core_roles')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'role_id']);
        });

        Schema::create('cs_core_user_creation_requests', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 36)->unique();
            $table->string('request_number', 30)->unique();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('cs_core_branches')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('cs_core_departments')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('cs_core_users')->restrictOnDelete();
            $table->foreignId('requested_role_id')->constrained('cs_core_roles')->restrictOnDelete();
            $table->string('full_name', 200);
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('position', 150)->nullable();
            $table->foreignId('direct_manager_id')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->enum('status', [
                'DRAFT', 'PENDING', 'IN_REVIEW', 'REVISION_REQUESTED', 'APPROVED', 'REJECTED', 'CANCELLED',
            ])->default('DRAFT');
            $table->unsignedSmallInteger('current_approval_level')->default(0);
            $table->foreignId('workflow_config_id')->constrained('cs_core_approval_workflow_configs')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('revision_notes')->nullable();
            $table->foreignId('created_user_id')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'company_id', 'status']);
            $table->index(['requested_by', 'status']);
        });

        Schema::create('cs_core_approval_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('cs_core_user_creation_requests')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order')->default(0);
            $table->enum('action', [
                'SUBMITTED', 'APPROVED', 'REJECTED', 'REVISION_REQUESTED', 'CANCELLED', 'OVERRIDDEN',
            ]);
            $table->foreignId('actor_id')->constrained('cs_core_users')->restrictOnDelete();
            $table->string('actor_role_code', 50)->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('cs_core_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('cs_core_companies')->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id');
            $table->char('entity_public_id', 36)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->string('actor_email', 255)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'event_type']);
        });

        Schema::create('cs_core_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('cs_core_users')->cascadeOnDelete();
            $table->enum('channel', ['IN_APP', 'EMAIL', 'WHATSAPP'])->default('IN_APP');
            $table->string('type', 80);
            $table->string('title', 200);
            $table->text('body');
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'read_at']);
        });

        Schema::table('cs_core_users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('default_branch_id')
                ->constrained('cs_core_departments')->nullOnDelete();
            $table->string('position', 150)->nullable()->after('department_id');
            $table->foreignId('direct_manager_id')->nullable()->after('position')
                ->constrained('cs_core_users')->nullOnDelete();
            $table->enum('provisioning_source', ['MANUAL', 'REQUEST_APPROVAL', 'REGISTRATION'])
                ->default('REGISTRATION')->after('direct_manager_id');
            $table->foreignId('provisioned_from_request_id')->nullable()->after('provisioning_source')
                ->constrained('cs_core_user_creation_requests')->nullOnDelete();
            $table->boolean('must_change_password')->default(false)->after('provisioned_from_request_id');
            $table->timestamp('activated_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('cs_core_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provisioned_from_request_id');
            $table->dropConstrainedForeignId('direct_manager_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['position', 'provisioning_source', 'must_change_password', 'activated_at']);
        });

        Schema::dropIfExists('cs_core_notifications');
        Schema::dropIfExists('cs_core_audit_logs');
        Schema::dropIfExists('cs_core_approval_history');
        Schema::dropIfExists('cs_core_user_creation_requests');
        Schema::dropIfExists('cs_core_department_role_mappings');
        Schema::dropIfExists('cs_core_approval_workflow_steps');
        Schema::dropIfExists('cs_core_approval_workflow_configs');
        Schema::dropIfExists('cs_core_departments');
    }
};