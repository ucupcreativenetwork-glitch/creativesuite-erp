<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Concerns\ValidatesTenantRelations;
use App\Modules\Business\Enums\StockMovementType;
use App\Modules\Business\Models\InvItem;
use App\Modules\Business\Models\InvStockBalance;
use App\Modules\Business\Models\InvStockMovement;
use App\Modules\Business\Models\InvWarehouse;
use App\Modules\Business\Models\PurchaseOrder;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\AccountMappingKey;
use App\Modules\Finance\Enums\JournalType;
use App\Modules\Finance\Services\AccountMappingService;
use App\Modules\Finance\Services\JournalService;
use Carbon\Carbon;
use App\Support\Business\ChecksPermissions;
use App\Support\Business\GeneratesDocumentNumber;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    use ChecksPermissions, GeneratesDocumentNumber, ValidatesTenantRelations;

    public function __construct(
        protected JournalService $journalService,
        protected AccountMappingService $accountMapping,
    ) {}

    public function listItems(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'inv.item.read');

        $query = InvItem::query()->orderBy('name');

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function showItem(User $user, string $publicId): InvItem
    {
        $this->assertPermission($user, 'inv.item.read');

        return InvItem::query()->where('public_id', $publicId)->firstOrFail();
    }

    public function createItem(User $user, array $data): InvItem
    {
        $this->assertPermission($user, 'inv.item.create');

        return InvItem::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'sku' => $data['sku'],
            'name' => $data['name'],
            'uom' => $data['uom'] ?? 'PCS',
            'unit_cost' => $data['unit_cost'] ?? 0,
            'reorder_level' => $data['reorder_level'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function updateItem(User $user, string $publicId, array $data): InvItem
    {
        $this->assertPermission($user, 'inv.item.update');

        $item = InvItem::query()->where('public_id', $publicId)->firstOrFail();
        $item->update(array_filter($data, fn ($v) => $v !== null));

        return $item->fresh();
    }

    public function deleteItem(User $user, string $publicId): void
    {
        $this->assertPermission($user, 'inv.item.delete');

        $item = InvItem::query()->where('public_id', $publicId)->firstOrFail();
        $item->delete();
    }

    public function listWarehouses(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'inv.warehouse.read');

        $query = InvWarehouse::query()->orderBy('name');

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function showWarehouse(User $user, string $publicId): InvWarehouse
    {
        $this->assertPermission($user, 'inv.warehouse.read');

        return InvWarehouse::query()->where('public_id', $publicId)->firstOrFail();
    }

    public function createWarehouse(User $user, array $data): InvWarehouse
    {
        $this->assertPermission($user, 'inv.warehouse.create');

        return InvWarehouse::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'branch_id' => $data['branch_id'] ?? $user->default_branch_id,
            'public_id' => (string) Str::uuid(),
            'code' => $data['code'],
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function updateWarehouse(User $user, string $publicId, array $data): InvWarehouse
    {
        $this->assertPermission($user, 'inv.warehouse.update');

        $warehouse = InvWarehouse::query()->where('public_id', $publicId)->firstOrFail();
        $warehouse->update(array_filter($data, fn ($v) => $v !== null));

        return $warehouse->fresh();
    }

    public function deleteWarehouse(User $user, string $publicId): void
    {
        $this->assertPermission($user, 'inv.warehouse.delete');

        $warehouse = InvWarehouse::query()->where('public_id', $publicId)->firstOrFail();
        $warehouse->delete();
    }

    public function listBalances(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'inv.balance.read');

        $query = InvStockBalance::query()
            ->with(['item', 'warehouse'])
            ->whereHas('item', fn ($q) => $q
                ->where('tenant_id', $user->tenant_id)
                ->where('company_id', $user->default_company_id))
            ->whereHas('warehouse', fn ($q) => $q
                ->where('tenant_id', $user->tenant_id)
                ->where('company_id', $user->default_company_id));

        if (! empty($filters['warehouse_id'])) {
            $this->assertWarehouseInScope($user, (int) $filters['warehouse_id']);
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['item_id'])) {
            $this->assertItemInScope($user, (int) $filters['item_id']);
            $query->where('item_id', $filters['item_id']);
        }

        if (! empty($filters['low_stock'])) {
            $query->whereHas('item', fn ($q) => $q
                ->whereColumn('cs_inv_stock_balances.quantity_on_hand', '<=', 'cs_inv_items.reorder_level'));
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function createMovement(User $user, array $data): InvStockMovement
    {
        $this->assertPermission($user, 'inv.movement.create');
        $this->assertItemInScope($user, $data['item_id']);
        $this->assertWarehouseInScope($user, $data['warehouse_id']);

        return $this->applyMovement($user, $data);
    }

    public function listMovements(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'inv.movement.read');

        $query = InvStockMovement::query()->with(['item', 'warehouse'])->orderByDesc('created_at');

        if (! empty($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function createStockIn(
        User $user,
        int $itemId,
        int $warehouseId,
        float $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        bool $skipJournal = false,
        ?float $unitCost = null,
    ): InvStockMovement {
        $this->assertItemInScope($user, $itemId);
        $this->assertWarehouseInScope($user, $warehouseId);

        return $this->applyMovement($user, [
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'movement_type' => StockMovementType::In->value,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'skip_journal' => $skipJournal,
            'unit_cost' => $unitCost,
        ]);
    }

    protected function applyMovement(User $user, array $data): InvStockMovement
    {
        return DB::transaction(function () use ($user, $data) {
            $quantity = (float) $data['quantity'];
            $type = StockMovementType::from($data['movement_type']);

            $balance = InvStockBalance::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'item_id' => $data['item_id'],
                        'warehouse_id' => $data['warehouse_id'],
                    ],
                    ['quantity_on_hand' => 0],
                );

            $newQty = (float) $balance->quantity_on_hand;

            match ($type) {
                StockMovementType::In => $newQty += $quantity,
                StockMovementType::Out => $newQty -= $quantity,
                StockMovementType::Adjust => $newQty += $quantity,
            };

            if ($newQty < 0) {
                throw new ApiException('Insufficient stock. Movement would result in negative balance.', 422, 'NEGATIVE_STOCK');
            }

            $movement = InvStockMovement::create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->default_company_id,
                'public_id' => (string) Str::uuid(),
                'movement_number' => $this->generateNumber(
                    new InvStockMovement,
                    $user->tenant_id,
                    $user->default_company_id,
                    'MOV-',
                    'movement_number',
                ),
                'item_id' => $data['item_id'],
                'warehouse_id' => $data['warehouse_id'],
                'movement_type' => $type,
                'quantity' => $quantity,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $balance->update(['quantity_on_hand' => $newQty]);

            $skipJournal = (bool) ($data['skip_journal'] ?? false)
                || (($data['reference_type'] ?? null) === PurchaseOrder::class);

            if (! $skipJournal) {
                $this->postInventoryJournal($user, $movement, $type, $quantity, $data);
            }

            return $movement->load(['item', 'warehouse']);
        });
    }

    protected function postInventoryJournal(
        User $user,
        InvStockMovement $movement,
        StockMovementType $type,
        float $quantity,
        array $data,
    ): void {
        $movement->loadMissing('item');
        $unitCost = (float) ($data['unit_cost'] ?? 0);
        if ($unitCost <= 0) {
            $unitCost = (float) ($movement->item?->unit_cost ?? 0);
        }

        $amount = round(abs($quantity) * $unitCost, 2);
        if ($amount <= 0) {
            return;
        }

        $companyId = $user->default_company_id;
        $inventoryId = $this->accountMapping->getAccountId($companyId, AccountMappingKey::InventoryAccount);
        $cogsId = $this->accountMapping->getAccountId($companyId, AccountMappingKey::CogsAccount);
        $expenseId = $this->accountMapping->getAccountId($companyId, AccountMappingKey::ExpenseAccount);

        $journalLines = match ($type) {
            StockMovementType::In => [
                ['account_id' => $inventoryId, 'debit' => $amount, 'credit' => 0, 'description' => 'Persediaan masuk'],
                ['account_id' => $expenseId, 'debit' => 0, 'credit' => $amount, 'description' => 'Kontra persediaan masuk'],
            ],
            StockMovementType::Out => [
                ['account_id' => $cogsId, 'debit' => $amount, 'credit' => 0, 'description' => 'HPP'],
                ['account_id' => $inventoryId, 'debit' => 0, 'credit' => $amount, 'description' => 'Persediaan keluar'],
            ],
            StockMovementType::Adjust => $quantity >= 0
                ? [
                    ['account_id' => $inventoryId, 'debit' => $amount, 'credit' => 0, 'description' => 'Penyesuaian persediaan (+)'],
                    ['account_id' => $expenseId, 'debit' => 0, 'credit' => $amount, 'description' => 'Kontra penyesuaian persediaan'],
                ]
                : [
                    ['account_id' => $expenseId, 'debit' => $amount, 'credit' => 0, 'description' => 'Penyesuaian persediaan (-)'],
                    ['account_id' => $inventoryId, 'debit' => 0, 'credit' => $amount, 'description' => 'Kontra penyesuaian persediaan'],
                ],
        };

        $this->journalService->createAuto(
            $user,
            JournalType::Inventory,
            Carbon::now(),
            $journalLines,
            "Inventory movement {$movement->movement_number}",
            InvStockMovement::class,
            $movement->id,
            $movement->movement_number,
        );
    }
}
