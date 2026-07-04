<?php

namespace App\Modules\Integration\Services;

use App\Modules\Business\Enums\PurchaseOrderStatus;
use App\Modules\Business\Models\InvStockBalance;
use App\Modules\Business\Services\PurchasingService;
use App\Modules\Core\Models\User;
use App\Modules\Integration\Enums\WebhookEvent;
use App\Modules\Integration\Models\AutoReorderRule;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutoReorderService
{
    use ChecksPermissions;

    public function __construct(
        protected PurchasingService $purchasingService,
        protected WebhookService $webhookService,
    ) {}

    public function list(User $user)
    {
        $this->assertPermission($user, 'int.auto_reorder.read');

        return AutoReorderRule::query()
            ->with(['vendor', 'warehouse'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(User $user, array $data): AutoReorderRule
    {
        $this->assertPermission($user, 'int.auto_reorder.manage');

        return AutoReorderRule::query()->create([
            'public_id' => (string) Str::uuid(),
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'name' => $data['name'],
            'vendor_id' => $data['vendor_id'] ?? null,
            'vendor_name' => $data['vendor_name'],
            'warehouse_id' => $data['warehouse_id'],
            'item_public_ids' => $data['item_public_ids'] ?? null,
            'order_multiplier' => $data['order_multiplier'] ?? 1,
            'auto_submit' => $data['auto_submit'] ?? false,
            'auto_approve' => $data['auto_approve'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
        ]);
    }

    public function update(User $user, string $publicId, array $data): AutoReorderRule
    {
        $this->assertPermission($user, 'int.auto_reorder.manage');

        $rule = $this->findScoped($user, $publicId);
        $rule->update(array_filter([
            'name' => $data['name'] ?? null,
            'vendor_id' => array_key_exists('vendor_id', $data) ? $data['vendor_id'] : null,
            'vendor_name' => $data['vendor_name'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'item_public_ids' => $data['item_public_ids'] ?? null,
            'order_multiplier' => $data['order_multiplier'] ?? null,
            'auto_submit' => $data['auto_submit'] ?? null,
            'auto_approve' => $data['auto_approve'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null));

        return $rule->fresh(['vendor', 'warehouse']);
    }

    public function destroy(User $user, string $publicId): void
    {
        $this->assertPermission($user, 'int.auto_reorder.manage');
        $this->findScoped($user, $publicId)->delete();
    }

    public function runAll(?User $triggeredBy = null): array
    {
        if ($triggeredBy) {
            $this->assertPermission($triggeredBy, 'int.auto_reorder.run');
        }

        $results = [];

        AutoReorderRule::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->get()
            ->each(function (AutoReorderRule $rule) use (&$results): void {
                $results[] = $this->processRule($rule);
            });

        return $results;
    }

    public function runForTenant(User $user): array
    {
        $this->assertPermission($user, 'int.auto_reorder.run');

        $results = [];

        AutoReorderRule::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->where('is_active', true)
            ->get()
            ->each(function (AutoReorderRule $rule) use (&$results): void {
                $results[] = $this->processRule($rule);
            });

        return $results;
    }

    public function processRule(AutoReorderRule $rule): array
    {
        $actor = User::query()->find($rule->created_by);
        if (! $actor) {
            return ['rule' => $rule->public_id, 'status' => 'skipped', 'reason' => 'no_actor'];
        }

        $lowStockItems = $this->lowStockItems($rule);

        if ($lowStockItems->isEmpty()) {
            return ['rule' => $rule->public_id, 'status' => 'skipped', 'reason' => 'no_low_stock'];
        }

        $this->webhookService->dispatch(
            $rule->tenant_id,
            $rule->company_id,
            WebhookEvent::InventoryLowStock,
            [
                'rule_public_id' => $rule->public_id,
                'items' => $lowStockItems->map(fn ($row) => [
                    'item_public_id' => $row->item_public_id,
                    'item_name' => $row->item_name,
                    'quantity_on_hand' => (float) $row->quantity_on_hand,
                    'reorder_level' => (float) $row->reorder_level,
                ])->values()->all(),
            ],
        );

        $lines = $lowStockItems->map(function ($row) use ($rule) {
            $qty = max(1, ($row->reorder_level - $row->quantity_on_hand) * (float) $rule->order_multiplier);

            return [
                'item_id' => $row->item_id,
                'description' => $row->item_name,
                'quantity' => round($qty, 4),
                'unit_price' => (float) ($row->unit_cost ?? 0),
            ];
        })->all();

        return DB::transaction(function () use ($actor, $rule, $lines, $lowStockItems) {
            $po = $this->purchasingService->create($actor, [
                'vendor_id' => $rule->vendor_id,
                'vendor_name' => $rule->vendor_name,
                'order_date' => now()->toDateString(),
                'notes' => "Auto-reorder: {$rule->name}",
                'lines' => $lines,
            ]);

            if ($rule->auto_submit) {
                $po = $this->purchasingService->submit($actor, $po->public_id);
            }
            if ($rule->auto_approve && $po->status === PurchaseOrderStatus::Submitted) {
                $po = $this->purchasingService->approve($actor, $po->public_id);
            }

            $this->webhookService->dispatch(
                $rule->tenant_id,
                $rule->company_id,
                WebhookEvent::PurchasingOrderCreated,
                [
                    'public_id' => $po->public_id,
                    'po_number' => $po->po_number,
                    'vendor_name' => $po->vendor_name,
                    'total_amount' => (float) $po->total_amount,
                    'status' => $po->status->value,
                    'source' => 'auto_reorder',
                    'item_count' => $lowStockItems->count(),
                ],
            );

            return [
                'rule' => $rule->public_id,
                'status' => 'created',
                'po_public_id' => $po->public_id,
                'po_number' => $po->po_number,
                'lines' => count($lines),
            ];
        });
    }

    protected function lowStockItems(AutoReorderRule $rule)
    {
        $query = InvStockBalance::query()
            ->join('cs_inv_items', 'cs_inv_items.id', '=', 'cs_inv_stock_balances.item_id')
            ->where('cs_inv_items.tenant_id', $rule->tenant_id)
            ->where('cs_inv_items.company_id', $rule->company_id)
            ->where('cs_inv_stock_balances.warehouse_id', $rule->warehouse_id)
            ->whereColumn('cs_inv_stock_balances.quantity_on_hand', '<=', 'cs_inv_items.reorder_level')
            ->where('cs_inv_items.reorder_level', '>', 0)
            ->select([
                'cs_inv_items.id as item_id',
                'cs_inv_items.public_id as item_public_id',
                'cs_inv_items.name as item_name',
                'cs_inv_items.reorder_level',
                'cs_inv_items.unit_cost',
                'cs_inv_stock_balances.quantity_on_hand',
            ]);

        if (! empty($rule->item_public_ids)) {
            $query->whereIn('cs_inv_items.public_id', $rule->item_public_ids);
        }

        return $query->get();
    }

    protected function findScoped(User $user, string $publicId): AutoReorderRule
    {
        return AutoReorderRule::query()
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}