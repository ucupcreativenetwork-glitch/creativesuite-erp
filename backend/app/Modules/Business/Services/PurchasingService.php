<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Concerns\ValidatesTenantRelations;
use App\Modules\Business\Enums\PurchaseOrderStatus;
use App\Modules\Business\Models\InvWarehouse;
use App\Modules\Business\Models\PurchaseOrder;
use App\Modules\Business\Models\PurchaseOrderLine;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Services\InvoiceService;
use App\Modules\Integration\Enums\WebhookEvent;
use App\Modules\Integration\Services\WebhookService;
use App\Support\Business\ChecksPermissions;
use App\Support\Business\GeneratesDocumentNumber;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchasingService
{
    use ChecksPermissions, GeneratesDocumentNumber, ValidatesTenantRelations;

    public function __construct(
        protected InventoryService $inventoryService,
        protected InvoiceService $invoiceService,
    ) {}

    public function list(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'pur.order.read');

        $query = PurchaseOrder::query()->with('lines')->orderByDesc('order_date');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function show(User $user, string $publicId): PurchaseOrder
    {
        $this->assertPermission($user, 'pur.order.read');

        return PurchaseOrder::query()
            ->where('public_id', $publicId)
            ->with(['lines.item', 'vendor'])
            ->firstOrFail();
    }

    public function create(User $user, array $data): PurchaseOrder
    {
        $this->assertPermission($user, 'pur.order.create');
        $this->assertAccountInScope($user, $data['vendor_id'] ?? null);

        foreach ($data['lines'] as $line) {
            $this->assertItemInScope($user, $line['item_id'] ?? null);
        }

        return DB::transaction(function () use ($user, $data) {
            $lines = $data['lines'];
            $subtotal = $this->calculateSubtotal($lines);

            $po = PurchaseOrder::create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->default_company_id,
                'public_id' => (string) Str::uuid(),
                'po_number' => $this->generateNumber(
                    new PurchaseOrder,
                    $user->tenant_id,
                    $user->default_company_id,
                    'PO-',
                    'po_number',
                ),
                'vendor_id' => $data['vendor_id'] ?? null,
                'vendor_name' => $data['vendor_name'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'status' => PurchaseOrderStatus::Draft,
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->syncLines($po, $lines);

            $po = $po->load('lines');
            $this->dispatchWebhook($user, WebhookEvent::PurchasingOrderCreated, $po);

            return $po;
        });
    }

    public function update(User $user, string $publicId, array $data): PurchaseOrder
    {
        $this->assertPermission($user, 'pur.order.update');

        $po = PurchaseOrder::query()->where('public_id', $publicId)->firstOrFail();

        if ($po->status !== PurchaseOrderStatus::Draft) {
            throw new ApiException('Only draft purchase orders can be updated.', 422, 'PO_NOT_DRAFT');
        }

        if (isset($data['vendor_id'])) {
            $this->assertAccountInScope($user, $data['vendor_id']);
        }

        return DB::transaction(function () use ($user, $po, $data) {
            if (isset($data['lines'])) {
                foreach ($data['lines'] as $line) {
                    $this->assertItemInScope($user, $line['item_id'] ?? null);
                }

                $subtotal = $this->calculateSubtotal($data['lines']);

                $po->update([
                    'vendor_id' => $data['vendor_id'] ?? $po->vendor_id,
                    'vendor_name' => $data['vendor_name'] ?? $po->vendor_name,
                    'order_date' => $data['order_date'] ?? $po->order_date,
                    'expected_date' => $data['expected_date'] ?? $po->expected_date,
                    'subtotal' => $subtotal,
                    'total_amount' => $subtotal,
                    'notes' => $data['notes'] ?? $po->notes,
                ]);

                $po->lines()->delete();
                $this->syncLines($po, $data['lines']);
            } else {
                $po->update(array_filter($data, fn ($v) => $v !== null));
            }

            return $po->fresh(['lines.item', 'vendor']);
        });
    }

    public function delete(User $user, string $publicId): void
    {
        $this->assertPermission($user, 'pur.order.delete');

        $po = PurchaseOrder::query()->where('public_id', $publicId)->firstOrFail();

        if ($po->status !== PurchaseOrderStatus::Draft) {
            throw new ApiException('Only draft purchase orders can be deleted.', 422, 'PO_NOT_DRAFT');
        }

        $po->delete();
    }

    public function submit(User $user, string $publicId): PurchaseOrder
    {
        $this->assertPermission($user, 'pur.order.submit');

        $po = PurchaseOrder::query()->where('public_id', $publicId)->firstOrFail();

        if ($po->status !== PurchaseOrderStatus::Draft) {
            throw new ApiException('Only draft purchase orders can be submitted.', 422, 'PO_NOT_DRAFT');
        }

        $po->update(['status' => PurchaseOrderStatus::Submitted]);

        return $po->fresh(['lines.item', 'vendor']);
    }

    public function approve(User $user, string $publicId): PurchaseOrder
    {
        $this->assertPermission($user, 'pur.order.approve');

        $po = PurchaseOrder::query()->where('public_id', $publicId)->firstOrFail();

        if ($po->status !== PurchaseOrderStatus::Submitted) {
            throw new ApiException('Only submitted purchase orders can be approved.', 422, 'PO_NOT_SUBMITTED');
        }

        $po->update(['status' => PurchaseOrderStatus::Approved]);

        return $po->fresh(['lines.item', 'vendor']);
    }

    public function receive(User $user, string $publicId, ?int $warehouseId = null): PurchaseOrder
    {
        $this->assertPermission($user, 'pur.order.receive');

        $warehouseId = $warehouseId ?? $this->resolveDefaultWarehouseId($user);
        $this->assertWarehouseInScope($user, $warehouseId);

        $po = PurchaseOrder::query()->where('public_id', $publicId)->with('lines')->firstOrFail();

        if ($po->status !== PurchaseOrderStatus::Approved) {
            throw new ApiException('Only approved purchase orders can be received.', 422, 'PO_NOT_APPROVED');
        }

        $existingInvoice = Invoice::query()->where('purchase_order_id', $po->id)->first();
        if ($existingInvoice) {
            if ($po->status !== PurchaseOrderStatus::Received) {
                $po->update([
                    'status' => PurchaseOrderStatus::Received,
                    'invoice_id' => $existingInvoice->id,
                ]);
            }

            return $po->fresh(['lines.item', 'vendor']);
        }

        return DB::transaction(function () use ($user, $po, $warehouseId) {
            foreach ($po->lines as $line) {
                if ($line->item_id) {
                    $this->inventoryService->createStockIn(
                        $user,
                        $line->item_id,
                        $warehouseId,
                        (float) $line->quantity,
                        PurchaseOrder::class,
                        $po->id,
                        "PO receipt {$po->po_number}",
                        skipJournal: true,
                        unitCost: (float) $line->unit_price,
                    );
                }
            }

            $invoice = $this->invoiceService->createAndPostFromPurchaseOrder($user, $po);

            $po->update([
                'status' => PurchaseOrderStatus::Received,
                'invoice_id' => $invoice->id,
            ]);

            $po = $po->fresh(['lines.item', 'vendor']);
            $this->dispatchWebhook($user, WebhookEvent::PurchasingOrderReceived, $po);

            return $po;
        });
    }

    protected function dispatchWebhook(User $user, WebhookEvent $event, PurchaseOrder $po): void
    {
        if (! $user->default_company_id) {
            return;
        }

        app(WebhookService::class)->dispatch(
            $user->tenant_id,
            $user->default_company_id,
            $event,
            [
                'public_id' => $po->public_id,
                'po_number' => $po->po_number,
                'vendor_name' => $po->vendor_name,
                'total_amount' => (float) $po->total_amount,
                'status' => $po->status->value ?? $po->status,
            ],
        );
    }

    protected function resolveDefaultWarehouseId(User $user): int
    {
        $warehouseId = InvWarehouse::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        if (! $warehouseId) {
            throw new ApiException(
                'No active warehouse found. Create a warehouse in Inventory before receiving goods.',
                422,
                'NO_WAREHOUSE',
            );
        }

        return (int) $warehouseId;
    }

    protected function calculateSubtotal(array $lines): float
    {
        return round(collect($lines)->sum(fn ($l) => round((float) ($l['quantity'] ?? 1) * (float) $l['unit_price'], 2)), 2);
    }

    protected function syncLines(PurchaseOrder $po, array $lines): void
    {
        foreach ($lines as $i => $line) {
            $qty = (float) ($line['quantity'] ?? 1);
            $price = (float) $line['unit_price'];

            PurchaseOrderLine::create([
                'purchase_order_id' => $po->id,
                'line_number' => $i + 1,
                'item_id' => $line['item_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $qty,
                'unit_price' => $price,
                'amount' => round($qty * $price, 2),
            ]);
        }
    }
}