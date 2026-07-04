<?php

namespace Tests\Feature\Platform;

use App\Modules\Core\Models\Tenant;
use App\Modules\Platform\Services\PlatformAdminService;
use App\Support\Platform\TenantPurgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_access_dashboard(): void
    {
        $service = app(PlatformAdminService::class);
        $admin = $service->createOrUpdateAdmin('super@platform.test', 'Password123');

        $token = auth('api')->login($admin);

        $this->withToken($token)
            ->getJson('/api/v1/platform/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_purge_demo_removes_pt_demo_tenant(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $this->assertTrue(Tenant::query()->where('slug', 'pt-demo')->exists());

        app(TenantPurgeService::class)->purgeBySlug('pt-demo');

        $this->assertFalse(Tenant::query()->where('slug', 'pt-demo')->exists());
    }

    public function test_cannot_purge_platform_system_tenant(): void
    {
        app(PlatformAdminService::class)->ensureSystemTenant();

        $this->expectException(\App\Support\Exceptions\ApiException::class);

        app(TenantPurgeService::class)->purgeBySlug('platform');
    }

    public function test_platform_admin_can_list_plans_and_update_tenant(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $service = app(PlatformAdminService::class);
        $admin = $service->createOrUpdateAdmin('admin@platform.test', 'Password123');
        $token = auth('api')->login($admin);

        $this->withToken($token)
            ->getJson('/api/v1/platform/plans')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');

        $tenant = Tenant::query()->where('slug', 'pt-demo')->firstOrFail();

        $this->withToken($token)
            ->patchJson("/api/v1/platform/tenants/{$tenant->public_id}", [
                'plan_code' => 'GROWTH',
                'max_users' => 25,
            ])
            ->assertOk()
            ->assertJsonPath('data.plan.code', 'GROWTH')
            ->assertJsonPath('data.max_users', 25);
    }

    public function test_platform_admin_can_seed_demo_after_purge(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $service = app(PlatformAdminService::class);
        $admin = $service->createOrUpdateAdmin('seed@platform.test', 'Password123');
        $token = auth('api')->login($admin);

        $this->withToken($token)
            ->postJson('/api/v1/platform/purge-demo')
            ->assertOk();

        $this->assertFalse(Tenant::query()->where('slug', 'pt-demo')->exists());

        $this->withToken($token)
            ->postJson('/api/v1/platform/seed-demo')
            ->assertOk()
            ->assertJsonPath('data.tenant_slug', 'pt-demo');

        $this->assertTrue(Tenant::query()->where('slug', 'pt-demo')->exists());

        $this->postJson('/api/v1/auth/login', [
            'company_name' => 'Demo Agency',
            'email' => 'admin@demo.id',
            'password' => 'Password123',
        ])->assertOk();
    }

    public function test_platform_admin_can_purge_demo_via_api(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $service = app(PlatformAdminService::class);
        $admin = $service->createOrUpdateAdmin('purge@platform.test', 'Password123');
        $token = auth('api')->login($admin);

        $this->withToken($token)
            ->postJson('/api/v1/platform/purge-demo')
            ->assertOk()
            ->assertJsonPath('data.tenant_slug', 'pt-demo');

        $this->assertFalse(Tenant::query()->where('slug', 'pt-demo')->exists());
    }
}