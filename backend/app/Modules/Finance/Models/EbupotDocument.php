<?php

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\TaxDocumentStatus;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbupotDocument extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_tax_ebupot_documents';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'pph23_transaction_id',
        'nomor_bupot',
        'status',
        'vendor_npwp',
        'vendor_name',
        'dpp',
        'pph23',
        'tanggal_bupot',
        'djp_reference',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaxDocumentStatus::class,
            'dpp' => 'decimal:2',
            'pph23' => 'decimal:2',
            'tanggal_bupot' => 'date',
            'issued_at' => 'datetime',
        ];
    }

    public function pph23Transaction(): BelongsTo
    {
        return $this->belongsTo(Pph23Transaction::class);
    }
}