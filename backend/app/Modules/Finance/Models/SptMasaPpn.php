<?php

namespace App\Modules\Finance\Models;

use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SptMasaPpn extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_tax_spt_masa_ppn';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'year',
        'month',
        'status',
        'total_pk',
        'total_pm',
        'kurang_lebih_bayar',
        'data_json',
        'finalized_at',
        'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'total_pk' => 'decimal:2',
            'total_pm' => 'decimal:2',
            'kurang_lebih_bayar' => 'decimal:2',
            'data_json' => 'array',
            'finalized_at' => 'datetime',
        ];
    }
}