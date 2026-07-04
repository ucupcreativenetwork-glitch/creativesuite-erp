<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_hr_employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('cs_hr_employees', 'contract_type')) {
                $table->string('contract_type', 20)->nullable()->after('hire_date');
            }
            if (! Schema::hasColumn('cs_hr_employees', 'contract_start')) {
                $table->date('contract_start')->nullable()->after('contract_type');
            }
            if (! Schema::hasColumn('cs_hr_employees', 'contract_end')) {
                $table->date('contract_end')->nullable()->after('contract_start');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cs_hr_employees', function (Blueprint $table): void {
            $table->dropColumn(['contract_type', 'contract_start', 'contract_end']);
        });
    }
};