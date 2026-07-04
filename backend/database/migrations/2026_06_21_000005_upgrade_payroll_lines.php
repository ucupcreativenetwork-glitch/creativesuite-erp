<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_hr_payroll_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('cs_hr_payroll_lines', 'attendance_deduction')) {
                $table->decimal('attendance_deduction', 18, 2)->default(0)->after('bpjs_amount');
            }
            if (! Schema::hasColumn('cs_hr_payroll_lines', 'overtime_amount')) {
                $table->decimal('overtime_amount', 18, 2)->default(0)->after('attendance_deduction');
            }
            if (! Schema::hasColumn('cs_hr_payroll_lines', 'allowance_amount')) {
                $table->decimal('allowance_amount', 18, 2)->default(0)->after('gross_salary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cs_hr_payroll_lines', function (Blueprint $table) {
            $table->dropColumn(['attendance_deduction', 'overtime_amount', 'allowance_amount']);
        });
    }
};