<?php

namespace App\Modules\Business\Models;

use App\Modules\Business\Enums\PayrollRunStatus;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use App\Modules\Finance\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollRun extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_hr_payroll_runs';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'run_number',
        'period_year',
        'period_month',
        'status',
        'total_gross',
        'total_deductions',
        'total_net',
        'journal_entry_id',
        'created_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayrollRunStatus::class,
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}