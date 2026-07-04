<?php

namespace App\Modules\Finance\Models;

use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMapping extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_fin_account_mappings';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'mapping_key',
        'account_id',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}