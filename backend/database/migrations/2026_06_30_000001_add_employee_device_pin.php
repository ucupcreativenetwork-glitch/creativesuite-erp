<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_hr_employees', function (Blueprint $table) {
            $table->string('device_pin', 40)->nullable()->after('employee_number');
            $table->unique(['company_id', 'device_pin'], 'cs_hr_employees_company_device_pin_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cs_hr_employees', function (Blueprint $table) {
            $table->dropUnique('cs_hr_employees_company_device_pin_unique');
            $table->dropColumn('device_pin');
        });
    }
};