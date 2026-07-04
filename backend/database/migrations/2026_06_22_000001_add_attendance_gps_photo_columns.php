<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_hr_attendance_records', function (Blueprint $table): void {
            $table->decimal('clock_in_latitude', 10, 7)->nullable()->after('clock_in_at');
            $table->decimal('clock_in_longitude', 10, 7)->nullable()->after('clock_in_latitude');
            $table->decimal('clock_in_accuracy_m', 8, 2)->nullable()->after('clock_in_longitude');
            $table->string('clock_in_photo_path', 500)->nullable()->after('clock_in_accuracy_m');

            $table->decimal('clock_out_latitude', 10, 7)->nullable()->after('clock_out_at');
            $table->decimal('clock_out_longitude', 10, 7)->nullable()->after('clock_out_latitude');
            $table->decimal('clock_out_accuracy_m', 8, 2)->nullable()->after('clock_out_longitude');
            $table->string('clock_out_photo_path', 500)->nullable()->after('clock_out_accuracy_m');
        });
    }

    public function down(): void
    {
        Schema::table('cs_hr_attendance_records', function (Blueprint $table): void {
            $table->dropColumn([
                'clock_in_latitude',
                'clock_in_longitude',
                'clock_in_accuracy_m',
                'clock_in_photo_path',
                'clock_out_latitude',
                'clock_out_longitude',
                'clock_out_accuracy_m',
                'clock_out_photo_path',
            ]);
        });
    }
};