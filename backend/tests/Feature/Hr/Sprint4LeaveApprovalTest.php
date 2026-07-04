<?php

namespace Tests\Feature\Hr;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Enums\LeaveRequestStatus;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Models\LeaveRequest;
use App\Modules\Core\Enums\EntityType;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyAccess;
use App\Modules\Iam\Models\IamNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class Sprint4LeaveApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_leader_can_approve_pending_leave_request(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $leave, $leaderToken] = $this->createPendingLeaveScenario();

        $this->withToken($leaderToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/leave-requests/{$leave->public_id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->assertDatabaseHas('cs_hr_leave_requests', [
            'id' => $leave->id,
            'status' => LeaveRequestStatus::Approved->value,
        ]);

        $this->assertDatabaseHas('cs_core_notifications', [
            'tenant_id' => $company->tenant_id,
            'type' => 'HR_LEAVE_APPROVED',
        ]);
    }

    public function test_leader_can_reject_pending_leave_request(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $leave, $leaderToken] = $this->createPendingLeaveScenario();

        $this->withToken($leaderToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/leave-requests/{$leave->public_id}/reject", [
                'reason' => 'Beban kerja tinggi',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'REJECTED')
            ->assertJsonPath('data.rejection_reason', 'Beban kerja tinggi');

        $notification = IamNotification::query()
            ->where('type', 'HR_LEAVE_REJECTED')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Beban kerja tinggi', $notification->body);
    }

    public function test_staff_cannot_approve_leave_request(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $leave, , $staffToken] = $this->createPendingLeaveScenario();

        $this->withToken($staffToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/leave-requests/{$leave->public_id}/approve")
            ->assertStatus(403);
    }

    protected function createPendingLeaveScenario(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Leave Approve',
            'slug' => 'leave-approve-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Leave Approve',
            'trade_name' => 'Leave Approve Co',
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

        $approvePerm = Permission::query()->where('code', 'hr.leave.approve')->firstOrFail();
        $createPerm = Permission::query()->where('code', 'hr.leave.create')->firstOrFail();

        $leaderRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'HEAD_HRD',
            'name' => 'Head HRD',
            'is_system' => false,
            'is_active' => true,
        ]);
        $leaderRole->permissions()->attach($approvePerm->id);

        $staffRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'STAFF',
            'name' => 'Staff',
            'is_system' => false,
            'is_active' => true,
        ]);
        $staffRole->permissions()->attach($createPerm->id);

        $leader = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'head@leave.id',
            'password' => 'Password123',
            'full_name' => 'Head HRD',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $leader->roles()->attach($leaderRole->id, ['tenant_id' => $tenant->id]);

        $staffUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'staff@leave.id',
            'password' => 'Password123',
            'full_name' => 'Staff Leave',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staffUser->roles()->attach($staffRole->id, ['tenant_id' => $tenant->id]);

        foreach ([$leader, $staffUser] as $user) {
            UserCompanyAccess::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'company_id' => $company->id,
                'is_default' => true,
            ]);
        }

        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'user_id' => $staffUser->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-LV-01',
            'full_name' => 'Staff Leave',
            'status' => EmployeeStatus::Active,
        ]);

        $leave = LeaveRequest::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'request_number' => 'LV-TEST-01',
            'employee_id' => $employee->id,
            'requested_by' => $staffUser->id,
            'leave_type' => 'ANNUAL',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'total_days' => 2,
            'reason' => 'Liburan keluarga',
            'status' => LeaveRequestStatus::Pending,
        ]);

        $this->assertTrue($leader->fresh()->hasPermission('hr.leave.approve'));

        return [
            $company,
            $leave,
            JWTAuth::fromUser($leader),
            JWTAuth::fromUser($staffUser),
        ];
    }
}