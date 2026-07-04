<?php

namespace Tests\Feature\Hr;

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

class Sprint2HrSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_and_read_hr_settings(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createAdminUser();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->putJson('/api/v1/hr/settings', [
                'work_start' => '09:00',
                'work_end' => '18:00',
                'late_grace_minutes' => 10,
                'require_gps' => true,
                'require_selfie' => false,
                'max_gps_accuracy_m' => 60,
            ])
            ->assertOk()
            ->assertJsonPath('data.work_start', '09:00')
            ->assertJsonPath('data.late_grace_minutes', 10)
            ->assertJsonPath('data.require_selfie', false);

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson('/api/v1/hr/settings')
            ->assertOk()
            ->assertJsonPath('data.work_end', '18:00')
            ->assertJsonPath('data.max_gps_accuracy_m', 60);
    }

    public function test_attendance_settings_includes_work_hours_from_company(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $user, $token] = $this->createAdminUser();
        $company->update([
            'settings' => [
                'hr' => [
                    'work_start' => '07:30',
                    'work_end' => '16:30',
                    'late_grace_minutes' => 20,
                ],
            ],
        ]);

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson('/api/v1/hr/attendance/settings')
            ->assertOk()
            ->assertJsonPath('data.work_start', '07:30')
            ->assertJsonPath('data.work_end', '16:30')
            ->assertJsonPath('data.late_grace_minutes', 20);
    }

    protected function createAdminUser(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT HR Settings',
            'slug' => 'hr-settings-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT HR Settings',
            'trade_name' => 'HR Settings Co',
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

        $perms = Permission::query()
            ->whereIn('code', ['core.company.read', 'core.company.update', 'hr.attendance.read'])
            ->pluck('id');

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'ADMIN',
            'name' => 'Admin',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($perms);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'admin@hrsettings.id',
            'password' => 'Password123',
            'full_name' => 'Admin HR',
            'default_company_id' => $company->id,
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