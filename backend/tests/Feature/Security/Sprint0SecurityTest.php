<?php

namespace Tests\Feature\Security;

use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\AttendanceRecord;
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
use Illuminate\Support\Str;
use Tests\TestCase;

class Sprint0SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_list_is_empty_when_user_has_no_employee_link(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $user, $token] = $this->createHrViewerWithoutEmployee();

        $employee = Employee::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-001',
            'full_name' => 'Karyawan Lain',
            'status' => EmployeeStatus::Active,
        ]);

        AttendanceRecord::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
        ]);

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson('/api/v1/hr/attendance')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_user_cannot_scope_requests_to_unauthorized_company(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Multi Company',
            'slug' => 'multi-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $companyA = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Alpha',
            'trade_name' => 'Alpha',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
        ]);

        $companyB = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Beta',
            'trade_name' => 'Beta',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $companyA->id,
            'code' => 'HQ',
            'name' => 'HQ',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $readPerm = Permission::query()->where('code', 'hr.attendance.read')->firstOrFail();

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'HR_VIEWER',
            'name' => 'HR Viewer',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($readPerm->id);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'viewer@alpha.id',
            'password' => 'Password123',
            'full_name' => 'HR Viewer',
            'default_company_id' => $companyA->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'company_id' => $companyA->id,
            'is_default' => true,
        ]);

        $token = auth('api')->login($user);

        $this->withToken($token)
            ->withHeader('X-Company-ID', $companyB->public_id)
            ->getJson('/api/v1/hr/attendance')
            ->assertForbidden()
            ->assertJsonPath('meta.error_code', 'COMPANY_ACCESS_DENIED');
    }

    public function test_webhook_creation_blocks_localhost_urls(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $login = $this->postJson('/api/v1/auth/login', [
            'company_name' => 'Demo Agency',
            'email' => 'admin@demo.id',
            'password' => 'Password123',
        ])->assertOk();

        $token = $login->json('data.access_token');
        $companyId = $login->json('data.company.id');

        $this->withToken($token)
            ->withHeader('X-Company-ID', $companyId)
            ->postJson('/api/v1/integrations/webhooks', [
                'name' => 'Bad Hook',
                'url' => 'http://127.0.0.1/hook',
                'events' => ['attendance.recorded'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('meta.error_code', 'URL_NOT_ALLOWED');
    }

    protected function createHrViewerWithoutEmployee(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Viewer Tenant',
            'slug' => 'viewer-tenant',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Viewer Co',
            'trade_name' => 'Viewer Co',
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

        $readPerm = Permission::query()->where('code', 'hr.attendance.read')->firstOrFail();

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'ATT_VIEWER',
            'name' => 'Attendance Viewer',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($readPerm->id);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'viewer@viewer.id',
            'password' => 'Password123',
            'full_name' => 'Viewer Tanpa Employee',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        return [$company, $user, auth('api')->login($user)];
    }
}