<?php

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Finance\Enums\JournalStatus;
use App\Modules\Finance\Enums\JournalType;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_fin_journal_entries';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'public_id',
        'entry_number',
        'entry_date',
        'journal_type',
        'status',
        'fiscal_period_id',
        'description',
        'reference_no',
        'source_type',
        'source_id',
        'total_debit',
        'total_credit',
        'posted_at',
        'posted_by',
        'reversal_of_id',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'journal_type' => JournalType::class,
            'status' => JournalStatus::class,
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('line_number');
    }
}