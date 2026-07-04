<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_core_branches', function (Blueprint $table): void {
            $table->boolean('attendance_geofence_enabled')->default(false)->after('is_active');
            $table->decimal('attendance_latitude', 10, 7)->nullable()->after('attendance_geofence_enabled');
            $table->decimal('attendance_longitude', 10, 7)->nullable()->after('attendance_latitude');
            $table->unsignedInteger('attendance_geofence_radius_m')->default(150)->after('attendance_longitude');
        });

        $demoTenantId = DB::table('cs_platform_tenants')->where('slug', 'pt-demo')->value('id');
        if ($demoTenantId) {
            DB::table('cs_core_branches')
                ->where('tenant_id', $demoTenantId)
                ->where('is_head_office', true)
                ->update([
                    'attendance_geofence_enabled' => true,
                    'attendance_latitude' => -6.208800,
                    'attendance_longitude' => 106.845600,
                    'attendance_geofence_radius_m' => 150,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('cs_core_branches', function (Blueprint $table): void {
            $table->dropColumn([
                'attendance_geofence_enabled',
                'attendance_latitude',
                'attendance_longitude',
                'attendance_geofence_radius_m',
            ]);
        });
    }
};