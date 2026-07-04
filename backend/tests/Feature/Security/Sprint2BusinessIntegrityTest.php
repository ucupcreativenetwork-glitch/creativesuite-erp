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
use App\Modules\Finance\Enums\JournalStatus;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Iam\Models\AuditLog;
use App\Modules\Platform\Services\PlatformAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Sprint2BusinessIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_journal_can_be_voided_with_reversal(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $token, $entry] = $this->createFinanceUserWithPostedJournal();

        $response = $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/finance/journals/{$entry->public_id}/void", [
                'reason' => 'Koreksi entri',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.voided.status', JournalStatus::Void->value)
            ->assertJsonPath('data.reversal.status', JournalStatus::Posted->value);

        $voided = JournalEntry::query()->where('public_id', $entry->public_id)->firstOrFail();
        $this->assertSame(JournalStatus::Void, $voided->status);
        $this->assertNotNull($voided->voided_at);

        $reversalPublicId = $response->json('data.reversal.public_id');
        $reversal = JournalEntry::query()->where('public_id', $reversalPublicId)->firstOrFail();
        $this->assertSame($entry->id, $reversal->reversal_of_id);
    }

    public function test_platform_tenant_purge_requires_confirmation(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $admin = app(PlatformAdminService::class)->createOrUpdateAdmin('purge@test.id', 'Password123');
        $token = auth('api')->login($admin);
        $tenant = Tenant::query()->where('slug', 'pt-demo')->firstOrFail();

        $this->withToken($token)
            ->deleteJson("/api/v1/platform/tenants/{$tenant->public_id}", [
                'confirmation' => 'SALAH',
            ])
            ->assertStatus(422)
            ->assertJsonPath('meta.error_code', 'PURGE_CONFIRMATION_REQUIRED');

        $this->assertTrue(Tenant::query()->where('slug', 'pt-demo')->exists());
    }

    public function test_platform_tenant_purge_writes_audit_log(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $admin = app(PlatformAdminService::class)->createOrUpdateAdmin('audit-purge@test.id', 'Password123');
        $token = auth('api')->login($admin);
        $tenant = Tenant::query()->where('slug', 'pt-demo')->firstOrFail();

        $this->withToken($token)
            ->deleteJson("/api/v1/platform/tenants/{$tenant->public_id}", [
                'confirmation' => 'DELETE:pt-demo',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertFalse(Tenant::query()->where('slug', 'pt-demo')->exists());
        $this->assertTrue(
            AuditLog::query()
                ->where('event_type', 'TENANT_PURGE')
                ->where('entity_public_id', $tenant->public_id)
                ->exists(),
        );
    }

    public function test_hr_me_returns_employee_profile(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createEmployeeUser();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson('/api/v1/hr/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee.employee_number', 'EMP-S2-001');
    }

    /**
     * @return array{0: Company, 1: string, 2: JournalEntry}
     */
    protected function createFinanceUserWithPostedJournal(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Finance Void',
            'slug' => 'fin-void',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Finance Void',
            'trade_name' => 'Finance Void',
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

        $perms = Permission::query()->whereIn('code', [
            'fin.journal.read',
            'fin.journal.create',
            'fin.journal.post',
            'fin.coa.read',
        ])->pluck('id');

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'FIN_ADMIN',
            'name' => 'Finance Admin',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($perms);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'finance@void.id',
            'password' => 'Password123',
            'full_name' => 'Finance Admin',
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

        $token = auth('api')->login($user);

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        app(\App\Modules\Finance\Services\CoaSetupService::class)->setupForCompany($tenant->id, $company->id);

        $accounts = \App\Modules\Finance\Models\ChartOfAccount::query()
            ->where('company_id', $company->id)
            ->where('is_postable', true)
            ->limit(2)
            ->get();

        $create = $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson('/api/v1/finance/journals', [
                'entry_date' => now()->toDateString(),
                'description' => 'Test void journal',
                'post_immediately' => true,
                'lines' => [
                    ['account_id' => $accounts[0]->id, 'debit' => 100000, 'credit' => 0],
                    ['account_id' => $accounts[1]->id, 'debit' => 0, 'credit' => 100000],
                ],
            ])
            ->assertCreated();

        $entry = JournalEntry::query()
            ->where('public_id', $create->json('data.public_id'))
            ->firstOrFail();

        return [$company, $token, $entry];
    }

    /**
     * @return array{0: Company, 1: User, 2: string}
     */
    protected function createEmployeeUser(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT HR Me',
            'slug' => 'hr-me',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT HR Me',
            'trade_name' => 'HR Me',
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
            'email' => 'staff@hrme.id',
            'password' => 'Password123',
            'full_name' => 'Staff HR',
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
            'employee_number' => 'EMP-S2-001',
            'full_name' => 'Staff HR',
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