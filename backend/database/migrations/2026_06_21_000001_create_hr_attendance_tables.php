<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_hr_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->char('public_id', 36)->unique();
            $table->foreignId('employee_id')->constrained('cs_hr_employees')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->timestamp('clock_in_at')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->string('status', 20)->default('PRESENT');
            $table->unsignedInteger('work_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'company_id', 'employee_id', 'attendance_date'], 'hr_attendance_unique_day');
            $table->index(['attendance_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_hr_attendance_records');
    }
};