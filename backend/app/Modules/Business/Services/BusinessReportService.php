<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Enums\PurchaseOrderStatus;
use App\Modules\Business\Enums\QuotationStatus;
use App\Modules\Business\Enums\TicketStatus;
use App\Modules\Business\Models\CrmAccount;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Models\InvItem;
use App\Modules\Business\Models\InvStockBalance;
use App\Modules\Business\Models\PurchaseOrder;
use App\Modules\Business\Models\Quotation;
use App\Modules\Business\Models\Ticket;
use App\Modules\Core\Models\User;
use App\Support\Business\ChecksPermissions;
use Illuminate\Support\Facades\DB;

class BusinessReportService
{
    use ChecksPermissions;

    public function dashboard(User $user): array
    {
        $this->assertPermission($user, 'rpt.dashboard.read');

        $companyId = $user->default_company_id;
        $tenantId = $user->tenant_id;

        $counts = [
            'accounts' => CrmAccount::query()->count(),
            'open_tickets' => Ticket::query()->whereNotIn('status', [
                TicketStatus::Resolved->value,
                TicketStatus::Closed->value,
            ])->count(),
            'active_employees' => Employee::query()->where('status', EmployeeStatus::Active)->count(),
            'inventory_items' => InvItem::query()->where('is_active', true)->count(),
            'pending_pos' => PurchaseOrder::query()->whereIn('status', [
                PurchaseOrderStatus::Submitted->value,
                PurchaseOrderStatus::Approved->value,
            ])->count(),
        ];

        $pipeline = Quotation::query()
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total_value'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
                'total_value' => (float) $row->total_value,
            ])
            ->values()
            ->all();

        $lowStock = InvStockBalance::query()
            ->join('cs_inv_items', 'cs_inv_items.id', '=', 'cs_inv_stock_balances.item_id')
            ->join('cs_inv_warehouses', 'cs_inv_warehouses.id', '=', 'cs_inv_stock_balances.warehouse_id')
            ->where('cs_inv_items.tenant_id', $tenantId)
            ->where('cs_inv_items.company_id', $companyId)
            ->where('cs_inv_items.is_active', true)
            ->whereColumn('cs_inv_stock_balances.quantity_on_hand', '<=', 'cs_inv_items.reorder_level')
            ->select([
                'cs_inv_items.sku',
                'cs_inv_items.name as item_name',
                'cs_inv_warehouses.name as warehouse_name',
                'cs_inv_stock_balances.quantity_on_hand',
                'cs_inv_items.reorder_level',
            ])
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'sku' => $row->sku,
                'item_name' => $row->item_name,
                'warehouse_name' => $row->warehouse_name,
                'quantity_on_hand' => (float) $row->quantity_on_hand,
                'reorder_level' => (float) $row->reorder_level,
            ])
            ->all();

        $recentQuotations = Quotation::query()
            ->whereIn('status', [QuotationStatus::Draft->value, QuotationStatus::Sent->value])
            ->orderByDesc('quotation_date')
            ->limit(5)
            ->get(['public_id', 'quotation_number', 'customer_name', 'status', 'total_amount', 'quotation_date'])
            ->map(fn ($q) => [
                'public_id' => $q->public_id,
                'quotation_number' => $q->quotation_number,
                'customer_name' => $q->customer_name,
                'status' => $q->status,
                'total_amount' => (float) $q->total_amount,
                'quotation_date' => $q->quotation_date?->format('Y-m-d'),
            ])
            ->all();

        return [
            'counts' => $counts,
            'pipeline' => $pipeline,
            'low_stock' => $lowStock,
            'recent_quotations' => $recentQuotations,
        ];
    }
}