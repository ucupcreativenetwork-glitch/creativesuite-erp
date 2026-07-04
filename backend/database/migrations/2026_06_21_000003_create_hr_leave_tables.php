<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_hr_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('request_number', 30);
            $table->foreignId('employee_id')->constrained('cs_hr_employees')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('cs_core_users')->restrictOnDelete();
            $table->string('leave_type', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('total_days')->default(1);
            $table->text('reason');
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('approved_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date']);
            $table->unique(['tenant_id', 'request_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_hr_leave_requests');
    }
};