<?php

namespace Tests\Feature\Finance;

use App\Modules\Business\Enums\StockMovementType;
use App\Modules\Business\Models\InvItem;
use App\Modules\Business\Models\InvStockMovement;
use App\Modules\Business\Models\InvWarehouse;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\JournalStatus;
use App\Modules\Finance\Enums\JournalType;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Services\CoaSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class InventoryJournalTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected InvItem $item;

    protected InvWarehouse $warehouse;

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
            'unit_cost' => 50000,
            'is_active' => true,
        ]);
    }

    public function test_stock_in_and_out_create_inventory_journals(): void
    {
        $stockInResponse = $this->withToken($this->token)
            ->postJson('/api/v1/inventory/movements', [
                'item_id' => $this->item->id,
                'warehouse_id' => $this->warehouse->id,
                'movement_type' => StockMovementType::In->value,
                'quantity' => 10,
                'notes' => 'Initial stock',
            ]);

        $stockInResponse->assertCreated();

        $inMovement = InvStockMovement::query()->latest('id')->firstOrFail();
        $inJournal = JournalEntry::query()
            ->where('source_type', InvStockMovement::class)
            ->where('source_id', $inMovement->id)
            ->first();

        $this->assertNotNull($inJournal);
        $this->assertSame(JournalType::Inventory, $inJournal->journal_type);
        $this->assertSame(JournalStatus::Posted, $inJournal->status);
        $this->assertSame(500000.0, (float) $inJournal->total_debit);

        $stockOutResponse = $this->withToken($this->token)
            ->postJson('/api/v1/inventory/movements', [
                'item_id' => $this->item->id,
                'warehouse_id' => $this->warehouse->id,
                'movement_type' => StockMovementType::Out->value,
                'quantity' => 3,
                'notes' => 'Project usage',
            ]);

        $stockOutResponse->assertCreated();

        $outMovement = InvStockMovement::query()->latest('id')->firstOrFail();
        $outJournal = JournalEntry::query()
            ->where('source_type', InvStockMovement::class)
            ->where('source_id', $outMovement->id)
            ->first();

        $this->assertNotNull($outJournal);
        $this->assertSame(JournalType::Inventory, $outJournal->journal_type);
        $this->assertSame(JournalStatus::Posted, $outJournal->status);
        $this->assertSame(150000.0, (float) $outJournal->total_debit);
    }
}