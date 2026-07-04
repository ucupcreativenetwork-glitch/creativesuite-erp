<?php

namespace Tests\Feature\Hr;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Services\HrNotificationService;
use App\Modules\Core\Enums\EntityType;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyAccess;
use App\Modules\Iam\Models\IamPushDevice;
use App\Modules\Iam\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class Sprint7PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['iam.expo_push.enabled' => true]);
    }

    public function test_user_can_register_push_token(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $user, $token] = $this->createStaffUser();
        $pushToken = 'ExponentPushToken[test-register-token]';

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson('/api/v1/notifications/push/register', [
                'expo_push_token' => $pushToken,
                'platform' => 'android',
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('cs_core_push_devices', [
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'expo_push_token' => $pushToken,
            'platform' => 'android',
        ]);
    }

    public function test_user_can_unregister_push_token(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $user, $token] = $this->createStaffUser();
        $pushToken = 'ExponentPushToken[test-unregister-token]';

        IamPushDevice::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'expo_push_token' => $pushToken,
            'platform' => 'android',
            'last_used_at' => now(),
        ]);

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->deleteJson('/api/v1/notifications/push/unregister', [
                'expo_push_token' => $pushToken,
            ])
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $this->assertDatabaseMissing('cs_core_push_devices', [
            'user_id' => $user->id,
            'expo_push_token' => $pushToken,
        ]);
    }

    public function test_register_reassigns_existing_token_to_current_user(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$companyA, $userA] = $this->createStaffUser('staff-a@push.id', 'push-co-a');
        [$companyB, $userB, $tokenB] = $this->createStaffUser('staff-b@push.id', 'push-co-b');
        $pushToken = 'ExponentPushToken[shared-device-token]';

        IamPushDevice::query()->create([
            'tenant_id' => $userA->tenant_id,
            'user_id' => $userA->id,
            'expo_push_token' => $pushToken,
            'platform' => 'android',
            'last_used_at' => now(),
        ]);

        $this->withToken($tokenB)
            ->withHeader('X-Company-ID', $companyB->public_id)
            ->postJson('/api/v1/notifications/push/register', [
                'expo_push_token' => $pushToken,
                'platform' => 'android',
            ])
            ->assertOk();

        $this->assertDatabaseHas('cs_core_push_devices', [
            'expo_push_token' => $pushToken,
            'user_id' => $userB->id,
        ]);
        $this->assertDatabaseMissing('cs_core_push_devices', [
            'expo_push_token' => $pushToken,
            'user_id' => $userA->id,
        ]);
    }

    public function test_hr_notification_triggers_expo_push(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [, , , $staffUser] = $this->createEmployeeScenario();
        $pushToken = 'ExponentPushToken[hr-reminder-token]';

        IamPushDevice::query()->create([
            'tenant_id' => $staffUser->tenant_id,
            'user_id' => $staffUser->id,
            'expo_push_token' => $pushToken,
            'platform' => 'android',
            'last_used_at' => now(),
        ]);

        Http::fake([
            'exp.host/*' => Http::response([
                'data' => [
                    ['status' => 'ok', 'id' => 'ticket-1'],
                ],
            ], 200),
        ]);

        app(HrNotificationService::class)->notifyClockInReminder($staffUser, '08:00');

        Http::assertSent(function ($request) use ($pushToken) {
            $body = $request->data();

            return str_contains($request->url(), 'exp.host')
                && is_array($body)
                && ($body[0]['to'] ?? null) === $pushToken
                && ($body[0]['title'] ?? null) === 'Pengingat absen masuk'
                && ($body[0]['data']['type'] ?? null) === 'HR_ATTENDANCE_REMINDER';
        });
    }

    public function test_non_hr_notification_does_not_trigger_expo_push(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [, $user] = $this->createStaffUser();
        $pushToken = 'ExponentPushToken[non-hr-token]';

        IamPushDevice::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'expo_push_token' => $pushToken,
            'platform' => 'android',
            'last_used_at' => now(),
        ]);

        Http::fake();

        app(NotificationDispatcher::class)->notifyUsers(
            collect([$user]),
            'USER_REQUEST_PENDING',
            'Permintaan user',
            'Menunggu persetujuan',
            ['request_public_id' => (string) Str::uuid()],
            sendEmail: false,
        );

        Http::assertNothingSent();
    }

    /**
     * @return array{0: Tenant, 1: Company, 2: Employee, 3: User}
     */
    protected function createEmployeeScenario(string $slug = 'push-co'): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Push',
            'slug' => $slug,
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Push',
            'trade_name' => 'Push Co',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
            'settings' => [
                'hr' => [
                    'work_start' => '08:00',
                    'work_end' => '17:00',
                    'clock_in_reminder_enabled' => true,
                    'clock_in_reminder_minutes' => 15,
                ],
            ],
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

        $staffUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'staff@push.id',
            'password' => 'Password123',
            'full_name' => 'Staff Push',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staffUser->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staffUser->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'user_id' => $staffUser->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-PS-01',
            'full_name' => 'Staff Push',
            'status' => EmployeeStatus::Active,
        ]);

        return [$tenant, $company, $employee, $staffUser];
    }

    /**
     * @return array{0: Company, 1: User, 2: string}
     */
    protected function createStaffUser(string $email = 'staff@push.id', string $slug = 'push-co'): array
    {
        [, $company, , $staffUser] = $this->createEmployeeScenario($slug);

        if ($email !== 'staff@push.id') {
            $staffUser->update(['email' => $email]);
        }

        return [$company, $staffUser, JWTAuth::fromUser($staffUser)];
    }
}