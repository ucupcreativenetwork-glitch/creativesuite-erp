<?php

namespace App\Modules\Integration\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Modules\Business\Models\InvStockBalance;
use App\Modules\Integration\Models\IntegrationApiKey;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function lowStock(Request $request)
    {
        /** @var IntegrationApiKey $apiKey */
        $apiKey = $request->attributes->get('integration_api_key');

        $rows = InvStockBalance::query()
            ->join('cs_inv_items', 'cs_inv_items.id', '=', 'cs_inv_stock_balances.item_id')
            ->where('cs_inv_items.tenant_id', $apiKey->tenant_id)
            ->where('cs_inv_items.company_id', $apiKey->company_id)
            ->whereColumn('cs_inv_stock_balances.quantity_on_hand', '<=', 'cs_inv_items.reorder_level')
            ->where('cs_inv_items.reorder_level', '>', 0)
            ->select([
                'cs_inv_items.public_id as item_public_id',
                'cs_inv_items.sku',
                'cs_inv_items.name as item_name',
                'cs_inv_items.reorder_level',
                'cs_inv_items.unit_cost',
                'cs_inv_stock_balances.quantity_on_hand',
                'cs_inv_stock_balances.warehouse_id',
            ])
            ->get()
            ->map(fn ($row) => [
                'item_public_id' => $row->item_public_id,
                'sku' => $row->sku,
                'item_name' => $row->item_name,
                'quantity_on_hand' => (float) $row->quantity_on_hand,
                'reorder_level' => (float) $row->reorder_level,
                'warehouse_id' => $row->warehouse_id,
            ]);

        return ApiResponse::success($rows);
    }
}