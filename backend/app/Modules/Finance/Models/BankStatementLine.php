<?php

namespace App\Modules\Finance\Models;

use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_fin_bank_statement_lines';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'bank_account_id',
        'transaction_date',
        'description',
        'reference_no',
        'debit',
        'credit',
        'matched_payment_id',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'bank_account_id');
    }

    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }
}