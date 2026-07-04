<?php

namespace App\Modules\Business\Models;

use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvItem extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_inv_items';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'sku',
        'name',
        'uom',
        'unit_cost',
        'reorder_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'reorder_level' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(InvStockBalance::class, 'item_id');
    }
}