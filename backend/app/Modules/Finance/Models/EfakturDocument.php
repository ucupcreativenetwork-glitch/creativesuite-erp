<?php

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\TaxDocumentStatus;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EfakturDocument extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_tax_efaktur_documents';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'ppn_transaction_id',
        'nomor_faktur',
        'status',
        'buyer_npwp',
        'buyer_name',
        'dpp',
        'ppn',
        'total',
        'tanggal_faktur',
        'djp_reference',
        'requested_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaxDocumentStatus::class,
            'dpp' => 'decimal:2',
            'ppn' => 'decimal:2',
            'total' => 'decimal:2',
            'tanggal_faktur' => 'date',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function ppnTransaction(): BelongsTo
    {
        return $this->belongsTo(PpnTransaction::class);
    }
}