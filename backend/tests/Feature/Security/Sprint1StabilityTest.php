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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Sprint1StabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_clock_in_uses_tenant_timezone_for_attendance_date(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-06-22 17:30:00', 'UTC'));

        [$company, $user, $token] = $this->createEmployeeWithClockPermission('Asia/Jakarta');

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson('/api/v1/hr/attendance/clock-in')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attendance_date', '2026-06-23');

        Carbon::setTestNow();
    }

    public function test_auth_refresh_returns_new_access_token(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createEmployeeWithClockPermission('Asia/Jakarta');

        $response = $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['access_token', 'token_type', 'expires_in'],
            ]);

        $newToken = $response->json('data.access_token');
        $this->assertNotEmpty($newToken);
        $this->assertNotSame($token, $newToken);

        $this->withToken($newToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    protected function createEmployeeWithClockPermission(string $timezone): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Attendance TZ',
            'slug' => 'att-tz',
            'status' => TenantStatus::Trial,
            'timezone' => $timezone,
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Attendance Co',
            'trade_name' => 'Attendance Co',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'HQ',
            'is_head_office' => true,
            'is_active' => true,
        ]);

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
            'email' => 'staff@attendance.id',
            'password' => 'Password123',
            'full_name' => 'Staff Absensi',
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
            'employee_number' => 'EMP-TZ-001',
            'full_name' => 'Staff Absensi',
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