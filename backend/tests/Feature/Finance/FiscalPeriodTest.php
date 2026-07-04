<?php

namespace Tests\Feature\Finance;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\FiscalPeriodStatus;
use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Finance\Services\CoaSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class FiscalPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $tenant = Tenant::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Co',
            'slug' => 'test-co',
            'status' => 'TRIAL',
            'max_users' => 10,
            'max_branches' => 1,
            'max_storage_mb' => 1024,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $this->company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'legal_name' => 'PT Test',
            'trade_name' => 'PT Test',
            'entity_type' => 'PT',
            'email' => 'test@test.id',
            'is_pkp' => true,
            'is_active' => true,
        ]);

        app(CoaSetupService::class)->setupForCompany($tenant->id, $this->company->id);

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'TENANT_OWNER',
            'name' => 'Owner',
            'is_system' => true,
            'is_active' => true,
        ]);
        $role->permissions()->sync(Permission::query()->pluck('id'));

        $this->user = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'email' => 'owner@test.id',
            'password' => 'Password123',
            'full_name' => 'Owner',
            'default_company_id' => $this->company->id,
            'is_active' => true,
        ]);
        $this->user->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_invoice_post_fails_when_fiscal_period_is_closed(): void
    {
        $invoiceResponse = $this->withToken($this->token)
            ->postJson('/api/v1/finance/invoices', [
                'invoice_type' => 'SALES',
                'invoice_date' => now()->format('Y-m-d'),
                'counterparty_name' => 'PT Klien',
                'counterparty_npwp' => '01.234.567.8-901.000',
                'is_ppn_inclusive' => true,
                'ppn_rate' => 12,
                'lines' => [
                    ['description' => 'Jasa konsultasi', 'quantity' => 1, 'unit_price' => 11200000],
                ],
            ]);

        $invoiceResponse->assertCreated();
        $publicId = $invoiceResponse->json('data.public_id');

        FiscalPeriod::query()
            ->where('company_id', $this->company->id)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->update([
                'status' => FiscalPeriodStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $this->user->id,
            ]);

        $postResponse = $this->withToken($this->token)
            ->postJson("/api/v1/finance/invoices/{$publicId}/post");

        $postResponse->assertStatus(422)
            ->assertJsonPath('meta.error_code', 'FISCAL_PERIOD_CLOSED');
    }
}