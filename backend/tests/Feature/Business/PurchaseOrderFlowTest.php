<?php

namespace Tests\Feature\Business;

use App\Modules\Business\Enums\PurchaseOrderStatus;
use App\Modules\Business\Models\CrmAccount;
use App\Modules\Business\Models\InvItem;
use App\Modules\Business\Models\InvWarehouse;
use App\Modules\Business\Models\PurchaseOrder;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\InvoiceStatus;
use App\Modules\Finance\Enums\InvoiceType;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Services\CoaSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PurchaseOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected CrmAccount $vendor;

    protected InvWarehouse $warehouse;

    protected InvItem $item;

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

        $this->vendor = CrmAccount::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'account_code' => 'V-TEST',
            'name' => 'PT Vendor Test',
            'account_type' => 'VENDOR',
            'status' => 'ACTIVE',
            'npwp' => '02.345.678.9-012.000',
        ]);

        $this->warehouse = InvWarehouse::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'code' => 'WH-TEST',
            'name' => 'Test Warehouse',
            'is_active' => true,
        ]);

        $this->item = InvItem::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'sku' => 'ITEM-001',
            'name' => 'Test Item',
            'unit_cost' => 100000,
            'is_active' => true,
        ]);
    }

    public function test_purchase_order_receive_creates_and_posts_ap_invoice(): void
    {
        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v1/purchasing/orders', [
                'vendor_id' => $this->vendor->id,
                'vendor_name' => $this->vendor->name,
                'order_date' => now()->format('Y-m-d'),
                'lines' => [[
                    'item_id' => $this->item->id,
                    'description' => $this->item->name,
                    'quantity' => 5,
                    'unit_price' => 100000,
                ]],
            ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.status', PurchaseOrderStatus::Draft->value);

        $publicId = $createResponse->json('data.public_id');

        $this->withToken($this->token)
            ->postJson("/api/v1/purchasing/orders/{$publicId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseOrderStatus::Submitted->value);

        $this->withToken($this->token)
            ->postJson("/api/v1/purchasing/orders/{$publicId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseOrderStatus::Approved->value);

        $receiveResponse = $this->withToken($this->token)
            ->postJson("/api/v1/purchasing/orders/{$publicId}/receive", [
                'warehouse_id' => $this->warehouse->id,
            ]);

        $receiveResponse->assertOk()
            ->assertJsonPath('data.status', PurchaseOrderStatus::Received->value);

        $po = PurchaseOrder::query()->where('public_id', $publicId)->firstOrFail();

        $invoice = Invoice::query()
            ->where('purchase_order_id', $po->id)
            ->where('invoice_type', InvoiceType::Purchase)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Posted, $invoice->status);
        $this->assertNotNull($invoice->journal_entry_id);
        $this->assertSame($this->vendor->name, $invoice->counterparty_name);
    }
}