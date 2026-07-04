<?php

namespace App\Modules\Business\Models;

use App\Modules\Business\Enums\StockMovementType;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvStockMovement extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_inv_stock_movements';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'movement_number',
        'item_id',
        'warehouse_id',
        'movement_type',
        'quantity',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'quantity' => 'decimal:4',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InvItem::class, 'item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InvWarehouse::class, 'warehouse_id');
    }
}