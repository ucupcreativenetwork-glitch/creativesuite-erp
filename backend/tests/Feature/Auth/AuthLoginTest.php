<?php

namespace Tests\Feature\Auth;

use App\Modules\Platform\Services\PlatformAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_company_trade_name(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $this->postJson('/api/v1/auth/login', [
            'company_name' => 'Demo Agency',
            'email' => 'admin@demo.id',
            'password' => 'Password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@demo.id');
    }

    public function test_login_with_tenant_name(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $this->postJson('/api/v1/auth/login', [
            'company_name' => 'PT Demo Agency',
            'email' => 'admin@demo.id',
            'password' => 'Password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_login_with_legacy_tenant_slug(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $this->postJson('/api/v1/auth/login', [
            'tenant_slug' => 'pt-demo',
            'email' => 'admin@demo.id',
            'password' => 'Password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_platform_admin_login_with_company_alias(): void
    {
        $service = app(PlatformAdminService::class);
        $service->createOrUpdateAdmin('superadmin@creativesuite.id', 'CreativeSuite2026!');

        $this->postJson('/api/v1/auth/login', [
            'company_name' => 'Admin SaaS',
            'email' => 'superadmin@creativesuite.id',
            'password' => 'CreativeSuite2026!',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.is_platform_admin', true);
    }

    public function test_login_with_demo_alias(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $this->postJson('/api/v1/auth/login', [
            'company_name' => 'Demo',
            'email' => 'admin@demo.id',
            'password' => 'Password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_login_rejects_invalid_company_name(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\DemoAgencySeeder::class);

        $this->postJson('/api/v1/auth/login', [
            'company_name' => 'Perusahaan Tidak Ada',
            'email' => 'admin@demo.id',
            'password' => 'Password123',
        ])
            ->assertStatus(401)
            ->assertJsonPath('meta.error_code', 'INVALID_CREDENTIALS');
    }
}