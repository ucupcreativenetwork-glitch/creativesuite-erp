<?php

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Finance\Enums\PaymentStatus;
use App\Modules\Finance\Enums\PaymentType;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_fin_payments';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'public_id',
        'payment_number',
        'payment_type',
        'payment_date',
        'invoice_id',
        'counterparty_name',
        'counterparty_npwp',
        'amount',
        'pph23_amount',
        'net_amount',
        'bank_account_id',
        'status',
        'journal_entry_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_type' => PaymentType::class,
            'payment_date' => 'date',
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'pph23_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'bank_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}