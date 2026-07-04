<?php

namespace Tests\Feature\Finance;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Services\CoaSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AccountingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

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

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'legal_name' => 'PT Test',
            'trade_name' => 'PT Test',
            'entity_type' => 'PT',
            'email' => 'test@test.id',
            'is_pkp' => true,
            'is_active' => true,
        ]);

        app(CoaSetupService::class)->setupForCompany($tenant->id, $company->id);

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
            'default_company_id' => $company->id,
            'is_active' => true,
        ]);
        $this->user->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_coa_tree_and_manual_journal_trial_balance(): void
    {
        $coaResponse = $this->withToken($this->token)
            ->getJson('/api/v1/finance/coa/tree');

        $coaResponse->assertOk()->assertJsonPath('success', true);

        $bank = ChartOfAccount::query()->where('code', '1-12-110')->first();
        $modal = ChartOfAccount::query()->where('code', '3-10-100')->first();

        $journalResponse = $this->withToken($this->token)
            ->postJson('/api/v1/finance/journals', [
                'entry_date' => now()->format('Y-m-d'),
                'description' => 'Setoran modal awal',
                'post_immediately' => true,
                'lines' => [
                    ['account_id' => $bank->id, 'debit' => 10000000, 'credit' => 0],
                    ['account_id' => $modal->id, 'debit' => 0, 'credit' => 10000000],
                ],
            ]);

        $journalResponse->assertCreated()->assertJsonPath('data.status', 'POSTED');

        $tbResponse = $this->withToken($this->token)
            ->getJson('/api/v1/finance/reports/trial-balance?to_date='.now()->format('Y-m-d'));

        $tbResponse->assertOk()
            ->assertJsonPath('data.totals.is_balanced', true);
    }

    public function test_sales_invoice_payment_and_tax_flow(): void
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
                    ['description' => 'Jasa instalasi CCTV', 'quantity' => 1, 'unit_price' => 11200000],
                ],
            ]);

        $invoiceResponse->assertCreated();
        $publicId = $invoiceResponse->json('data.public_id');

        $postResponse = $this->withToken($this->token)
            ->postJson("/api/v1/finance/invoices/{$publicId}/post");

        $postResponse->assertOk()
            ->assertJsonPath('data.status', 'POSTED')
            ->assertJsonStructure(['data' => ['journal_entry' => ['entry_number']]]);

        $bank = ChartOfAccount::query()->where('code', '1-12-110')->first();

        $paymentResponse = $this->withToken($this->token)
            ->postJson('/api/v1/finance/payments', [
                'payment_type' => 'AR_RECEIPT',
                'payment_date' => now()->format('Y-m-d'),
                'amount' => 11200000,
                'bank_account_id' => $bank->id,
                'invoice_id' => $postResponse->json('data.id'),
            ]);

        $paymentResponse->assertCreated();
        $paymentPublicId = $paymentResponse->json('data.public_id');

        $this->withToken($this->token)
            ->postJson("/api/v1/finance/payments/{$paymentPublicId}/post")
            ->assertOk();

        $ppnTxn = $this->withToken($this->token)
            ->getJson('/api/v1/finance/tax/ppn/transactions')
            ->assertOk()
            ->json('data.0');

        $this->assertNotNull($ppnTxn);

        $this->withToken($this->token)
            ->postJson("/api/v1/finance/tax/efaktur/{$ppnTxn['id']}/request")
            ->assertCreated();

        $year = (int) now()->year;
        $month = (int) now()->month;

        $this->withToken($this->token)
            ->postJson('/api/v1/finance/tax/spt-ppn/generate', ['year' => $year, 'month' => $month])
            ->assertOk()
            ->assertJsonPath('data.total_pk', $ppnTxn['ppn_amount']);
    }

    public function test_ap_payment_with_pph23_and_ebupot(): void
    {
        $invoiceResponse = $this->withToken($this->token)
            ->postJson('/api/v1/finance/invoices', [
                'invoice_type' => 'PURCHASE',
                'invoice_date' => now()->format('Y-m-d'),
                'counterparty_name' => 'PT Vendor',
                'counterparty_npwp' => '02.345.678.9-012.000',
                'ppn_rate' => 12,
                'lines' => [
                    ['description' => 'Jasa konsultan IT', 'quantity' => 1, 'unit_price' => 10000000],
                ],
            ]);

        $invoiceResponse->assertCreated();
        $publicId = $invoiceResponse->json('data.public_id');

        $this->withToken($this->token)
            ->postJson("/api/v1/finance/invoices/{$publicId}/post")
            ->assertOk();

        $bank = ChartOfAccount::query()->where('code', '1-12-110')->first();

        $invoiceId = $this->withToken($this->token)
            ->getJson("/api/v1/finance/invoices/{$publicId}")
            ->json('data.id');

        $paymentResponse = $this->withToken($this->token)
            ->postJson('/api/v1/finance/payments', [
                'payment_type' => 'AP_DISBURSEMENT',
                'payment_date' => now()->format('Y-m-d'),
                'amount' => 11200000,
                'invoice_id' => $invoiceId,
                'bank_account_id' => $bank->id,
                'counterparty_name' => 'PT Vendor',
                'counterparty_npwp' => '02.345.678.9-012.000',
                'apply_pph23' => true,
            ]);

        $paymentResponse->assertCreated();
        $paymentPublicId = $paymentResponse->json('data.public_id');

        $this->withToken($this->token)
            ->postJson("/api/v1/finance/payments/{$paymentPublicId}/post")
            ->assertOk()
            ->assertJsonPath('data.pph23_amount', 200000);

        $pph23Txn = $this->withToken($this->token)
            ->getJson('/api/v1/finance/tax/pph23/transactions')
            ->assertOk()
            ->json('data.0');

        $this->withToken($this->token)
            ->postJson("/api/v1/finance/tax/ebupot/{$pph23Txn['id']}/issue")
            ->assertCreated()
            ->assertJsonPath('data.status', 'ISSUED');
    }
}