<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cs_hr_leave_requests')) {
            Schema::table('cs_hr_leave_requests', function (Blueprint $table): void {
                $table->string('leave_type', 30)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cs_hr_leave_requests')) {
            Schema::table('cs_hr_leave_requests', function (Blueprint $table): void {
                $table->string('leave_type', 20)->change();
            });
        }
    }
};