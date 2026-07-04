<?php

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\PpnTransactionType;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpnTransaction extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_tax_ppn_transactions';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'source_type',
        'source_id',
        'transaction_type',
        'transaction_date',
        'fiscal_year',
        'fiscal_month',
        'dpp_amount',
        'ppn_rate',
        'ppn_amount',
        'counterparty_name',
        'counterparty_npwp',
        'efaktur_document_id',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => PpnTransactionType::class,
            'transaction_date' => 'date',
            'dpp_amount' => 'decimal:2',
            'ppn_rate' => 'decimal:2',
            'ppn_amount' => 'decimal:2',
        ];
    }

    public function efakturDocument(): BelongsTo
    {
        return $this->belongsTo(EfakturDocument::class);
    }
}