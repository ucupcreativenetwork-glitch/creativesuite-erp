<?php

namespace App\Modules\Integration\Models;

use App\Modules\Business\Models\CrmAccount;
use App\Modules\Business\Models\InvWarehouse;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoReorderRule extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_int_auto_reorder_rules';

    protected $fillable = [
        'public_id', 'tenant_id', 'company_id', 'name', 'vendor_id', 'vendor_name',
        'warehouse_id', 'item_public_ids', 'order_multiplier', 'auto_submit',
        'auto_approve', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'item_public_ids' => 'array',
            'order_multiplier' => 'decimal:2',
            'auto_submit' => 'boolean',
            'auto_approve' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'vendor_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InvWarehouse::class, 'warehouse_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}