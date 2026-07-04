<?php

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\FiscalPeriodStatus;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FiscalPeriod extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_fin_fiscal_periods';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'year',
        'month',
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => FiscalPeriodStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }
}