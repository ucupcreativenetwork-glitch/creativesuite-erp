<?php

namespace App\Modules\Business\Models;

use App\Modules\Business\Enums\TicketPriority;
use App\Modules\Business\Enums\TicketStatus;
use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_ops_tickets';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'ticket_number',
        'account_id',
        'subject',
        'description',
        'priority',
        'status',
        'assigned_to',
        'created_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}