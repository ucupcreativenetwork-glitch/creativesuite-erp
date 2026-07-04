<?php

namespace Tests\Feature\Security;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\Employee;
use App\Modules\Core\Enums\EntityType;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config([
            'hr.attendance_capture.require_gps' => true,
            'hr.attendance_capture.require_selfie' => true,
            'hr.attendance_capture.strict_mobile_only' => true,
            'hr.attendance_capture.max_gps_accuracy_m' => 80,
            'hr.attendance_capture.geofence_enabled' => false,
        ]);
    }

    public function test_mobile_clock_in_stores_gps_and_selfie(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createEmployeeUser();

        $photo = UploadedFile::fake()->create('selfie.jpg', 100, 'image/jpeg');

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->withHeader('X-Client-App', 'creativesuite-hr-mobile')
            ->post('/api/v1/hr/attendance/clock-in', [
                'latitude' => -6.200000,
                'longitude' => 106.816666,
                'accuracy_m' => 12.5,
                'photo' => $photo,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.clock_in_latitude', -6.2)
            ->assertJsonPath('data.clock_in_accuracy_m', 12.5)
            ->assertJsonStructure(['data' => ['clock_in_photo_url']]);
    }

    public function test_mobile_clock_in_rejects_low_gps_accuracy(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createEmployeeUser();
        $photo = UploadedFile::fake()->create('selfie.jpg', 100, 'image/jpeg');

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->withHeader('X-Client-App', 'creativesuite-hr-mobile')
            ->post('/api/v1/hr/attendance/clock-in', [
                'latitude' => -6.200000,
                'longitude' => 106.816666,
                'accuracy_m' => 120,
                'photo' => $photo,
            ])
            ->assertStatus(422)
            ->assertJsonPath('meta.error_code', 'GPS_ACCURACY_TOO_LOW');
    }

    public function test_web_clock_in_does_not_require_gps_or_selfie(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createEmployeeUser();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson('/api/v1/hr/attendance/clock-in', [])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_attendance_settings_endpoint_returns_capture_policy(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createEmployeeUser();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson('/api/v1/hr/attendance/settings')
            ->assertOk()
            ->assertJsonPath('data.require_gps', true)
            ->assertJsonPath('data.require_selfie', true)
            ->assertJsonPath('data.max_gps_accuracy_m', 80);
    }

    public function test_mobile_clock_in_rejects_out_of_geofence_range(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createEmployeeUser([
            'attendance_geofence_enabled' => true,
            'attendance_latitude' => -6.208800,
            'attendance_longitude' => 106.845600,
            'attendance_geofence_radius_m' => 150,
        ]);

        $photo = UploadedFile::fake()->create('selfie.jpg', 100, 'image/jpeg');

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->withHeader('X-Client-App', 'creativesuite-hr-mobile')
            ->post('/api/v1/hr/attendance/clock-in', [
                'latitude' => -6.200000,
                'longitude' => 106.816666,
                'accuracy_m' => 12.5,
                'photo' => $photo,
            ])
            ->assertStatus(422)
            ->assertJsonPath('meta.error_code', 'GEOFENCE_OUT_OF_RANGE');
    }

    public function test_mobile_clock_in_accepts_within_geofence_radius(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createEmployeeUser([
            'attendance_geofence_enabled' => true,
            'attendance_latitude' => -6.208800,
            'attendance_longitude' => 106.845600,
            'attendance_geofence_radius_m' => 150,
        ]);

        $photo = UploadedFile::fake()->create('selfie.jpg', 100, 'image/jpeg');

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->withHeader('X-Client-App', 'creativesuite-hr-mobile')
            ->post('/api/v1/hr/attendance/clock-in', [
                'latitude' => -6.208800,
                'longitude' => 106.845600,
                'accuracy_m' => 12.5,
                'photo' => $photo,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_attendance_settings_returns_branch_geofence(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createEmployeeUser([
            'attendance_geofence_enabled' => true,
            'attendance_latitude' => -6.208800,
            'attendance_longitude' => 106.845600,
            'attendance_geofence_radius_m' => 200,
        ]);

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson('/api/v1/hr/attendance/settings')
            ->assertOk()
            ->assertJsonPath('data.geofence_enabled', true)
            ->assertJsonPath('data.geofence_radius_m', 200)
            ->assertJsonPath('data.geofence_latitude', -6.2088)
            ->assertJsonPath('data.geofence_longitude', 106.8456)
            ->assertJsonPath('data.geofence_branch_name', 'HQ')
            ->assertJsonPath('data.geofence_source', 'branch');
    }

    /**
     * @param  array<string, mixed>  $branchOverrides
     * @return array{0: Company, 1: User, 2: string}
     */
    protected function createEmployeeUser(array $branchOverrides = []): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Capture',
            'slug' => 'capture-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Capture',
            'trade_name' => 'Capture Co',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
        ]);

        $branch = Branch::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'HQ',
            'is_head_office' => true,
            'is_active' => true,
        ], $branchOverrides));

        $clockPerm = Permission::query()->where('code', 'hr.attendance.clock')->firstOrFail();

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'STAFF',
            'name' => 'Staff',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($clockPerm->id);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'staff@capture.id',
            'password' => 'Password123',
            'full_name' => 'Staff Capture',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'user_id' => $user->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-CAP-01',
            'full_name' => 'Staff Capture',
            'status' => EmployeeStatus::Active,
        ]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        return [$company, $user, auth('api')->login($user)];
    }
}