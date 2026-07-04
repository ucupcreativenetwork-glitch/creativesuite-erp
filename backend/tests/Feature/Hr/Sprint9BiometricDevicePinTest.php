<?php

namespace Tests\Feature\Hr;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Services\PayrollService;
use App\Modules\Core\Enums\EntityType;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyAccess;
use App\Modules\Integration\Services\ConnectorService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class Sprint9BiometricDevicePinTest extends TestCase
{
    use RefreshDatabase;

    public function test_connector_matches_employee_by_device_pin(): void
    {
        $this->seed(PermissionSeeder::class);

        [$owner, $employee] = $this->createOwnerWithEmployee(devicePin: '8801');

        $result = app(ConnectorService::class)->create($owner, [
            'name' => 'ZKTeco Lobby',
            'connector_type' => 'zkteco',
            'employee_match_field' => 'device_pin',
        ]);

        $this->withHeader('X-Connector-Token', $result['ingest_token'])
            ->postJson('/api/v1/external/connectors/push', [
                'PIN' => '8801',
                'DateTime' => now()->setTime(8, 5)->format('Y-m-d H:i:s'),
                'Status' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 1);

        $this->assertDatabaseHas('cs_hr_attendance_records', [
            'employee_id' => $employee->id,
            'source' => 'zkteco',
        ]);
    }

    public function test_hikvision_matches_device_pin_field(): void
    {
        $this->seed(PermissionSeeder::class);

        [$owner, $employee] = $this->createOwnerWithEmployee(devicePin: 'HV-42');

        $result = app(ConnectorService::class)->create($owner, [
            'name' => 'Hikvision Gate',
            'connector_type' => 'hikvision',
            'employee_match_field' => 'device_pin',
        ]);

        $this->withHeader('X-Connector-Token', $result['ingest_token'])
            ->postJson('/api/v1/external/connectors/push', [
                'AccessControllerEvent' => [
                    'employeeNoString' => 'HV-42',
                    'dateTime' => now()->setTime(8, 10)->format('Y-m-d\TH:i:sP'),
                    'attendanceStatus' => 'checkIn',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 1);

        $record = AttendanceRecord::query()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($record?->clock_in_at);
    }

    public function test_admin_can_bulk_update_device_pins(): void
    {
        $this->seed(PermissionSeeder::class);

        [$company, $employee, $token] = $this->createHrAdminWithEmployee();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->putJson('/api/v1/hr/employees/device-pins', [
                'mappings' => [
                    ['public_id' => $employee->public_id, 'device_pin' => '9901'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertDatabaseHas('cs_hr_employees', [
            'id' => $employee->id,
            'device_pin' => '9901',
        ]);
    }

    public function test_device_pin_must_be_unique_per_company(): void
    {
        $this->seed(PermissionSeeder::class);

        [$company, $employeeA, $employeeB, $token] = $this->createTwoEmployeesScenario();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->putJson('/api/v1/hr/employees/device-pins', [
                'mappings' => [
                    ['public_id' => $employeeA->public_id, 'device_pin' => 'DUPE-01'],
                    ['public_id' => $employeeB->public_id, 'device_pin' => 'DUPE-01'],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_integrations_meta_lists_connector_match_fields(): void
    {
        $this->seed(PermissionSeeder::class);

        [$owner] = $this->createOwnerWithEmployee();

        $token = JWTAuth::fromUser($owner);

        $this->withToken($token)
            ->getJson('/api/v1/integrations/meta')
            ->assertOk()
            ->assertJsonPath('data.connector_match_fields.device_pin', 'PIN Mesin Absensi');
    }

    /**
     * @return array{0: User, 1: Employee}
     */
    protected function createOwnerWithEmployee(?string $devicePin = null): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Biometric',
            'slug' => 'biometric-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Biometric',
            'trade_name' => 'Biometric Co',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
        ]);

        Branch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'HQ',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $connectorPerms = Permission::query()
            ->whereIn('code', ['int.connector.read', 'int.connector.manage'])
            ->pluck('id');

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'OWNER',
            'name' => 'Owner',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($connectorPerms);

        $owner = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'owner@biometric.id',
            'password' => 'Password123',
            'full_name' => 'Owner Biometric',
            'default_company_id' => $company->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $owner->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-BIO-01',
            'device_pin' => $devicePin,
            'full_name' => 'Staff Biometric',
            'status' => EmployeeStatus::Active,
            'base_salary' => 8000000,
        ]);

        return [$owner, $employee];
    }

    /**
     * @return array{0: Company, 1: Employee, 2: string}
     */
    protected function createHrAdminWithEmployee(): array
    {
        [$owner, $employee] = $this->createOwnerWithEmployee();
        $company = Company::query()->find($owner->default_company_id);

        $perms = Permission::query()
            ->whereIn('code', ['hr.employee.read', 'hr.employee.update'])
            ->pluck('id');

        $role = Role::query()->create([
            'tenant_id' => $owner->tenant_id,
            'code' => 'HR_ADMIN',
            'name' => 'HR Admin',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($perms);
        $owner->roles()->attach($role->id, ['tenant_id' => $owner->tenant_id]);

        return [$company, $employee, JWTAuth::fromUser($owner)];
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee, 3: string}
     */
    protected function createTwoEmployeesScenario(): array
    {
        [$owner, $employeeA] = $this->createOwnerWithEmployee();
        $company = Company::query()->find($owner->default_company_id);

        $employeeB = Employee::query()->create([
            'tenant_id' => $owner->tenant_id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-BIO-02',
            'full_name' => 'Staff Biometric 2',
            'status' => EmployeeStatus::Active,
            'base_salary' => 7000000,
        ]);

        $perms = Permission::query()
            ->whereIn('code', ['hr.employee.read', 'hr.employee.update'])
            ->pluck('id');

        $role = Role::query()->create([
            'tenant_id' => $owner->tenant_id,
            'code' => 'HR_ADMIN2',
            'name' => 'HR Admin 2',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($perms);
        $owner->roles()->attach($role->id, ['tenant_id' => $owner->tenant_id]);

        return [$company, $employeeA, $employeeB, JWTAuth::fromUser($owner)];
    }
}