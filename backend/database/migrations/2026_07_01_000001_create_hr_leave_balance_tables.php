<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_hr_leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('cs_hr_employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('base_entitlement', 6, 2)->default(0);
            $table->decimal('carried_forward', 6, 2)->default(0);
            $table->decimal('adjustment', 6, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'year']);
            $table->index(['company_id', 'year']);
        });

        Schema::create('cs_hr_leave_balance_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('cs_hr_employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('entry_type', 20);
            $table->decimal('days', 6, 2);
            $table->foreignId('leave_request_id')->nullable()->constrained('cs_hr_leave_requests')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'year', 'entry_type']);
            $table->index(['company_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_hr_leave_balance_ledger');
        Schema::dropIfExists('cs_hr_leave_entitlements');
    }
};