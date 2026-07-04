<?php

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Finance\Enums\InvoiceStatus;
use App\Modules\Finance\Enums\InvoiceType;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_fin_invoices';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'quotation_id',
        'project_id',
        'purchase_order_id',
        'public_id',
        'invoice_number',
        'invoice_type',
        'invoice_date',
        'due_date',
        'counterparty_name',
        'counterparty_npwp',
        'counterparty_phone',
        'status',
        'subtotal',
        'dpp_amount',
        'ppn_rate',
        'ppn_amount',
        'pph23_amount',
        'total_amount',
        'paid_amount',
        'is_ppn_inclusive',
        'is_pph23_applicable',
        'notes',
        'journal_entry_id',
        'posted_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_type' => InvoiceType::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'subtotal' => 'decimal:2',
            'dpp_amount' => 'decimal:2',
            'ppn_rate' => 'decimal:2',
            'ppn_amount' => 'decimal:2',
            'pph23_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'is_ppn_inclusive' => 'boolean',
            'is_pph23_applicable' => 'boolean',
            'posted_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('line_number');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}