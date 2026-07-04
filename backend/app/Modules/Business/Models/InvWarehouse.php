<?php

namespace App\Modules\Business\Models;

use App\Modules\Core\Models\Branch;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvWarehouse extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_inv_warehouses';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'public_id',
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(InvStockBalance::class, 'warehouse_id');
    }
}