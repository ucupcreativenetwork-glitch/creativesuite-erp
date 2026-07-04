<?php

namespace App\Modules\Finance\Models;

use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pph23Transaction extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_tax_pph23_transactions';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'source_type',
        'source_id',
        'transaction_date',
        'fiscal_year',
        'fiscal_month',
        'vendor_npwp',
        'vendor_name',
        'dpp_amount',
        'pph23_rate',
        'pph23_amount',
        'ebupot_document_id',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'dpp_amount' => 'decimal:2',
            'pph23_rate' => 'decimal:2',
            'pph23_amount' => 'decimal:2',
        ];
    }

    public function ebupotDocument(): BelongsTo
    {
        return $this->belongsTo(EbupotDocument::class);
    }
}