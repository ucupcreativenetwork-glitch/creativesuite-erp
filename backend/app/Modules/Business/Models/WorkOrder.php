<?php

namespace App\Modules\Business\Models;

use App\Modules\Business\Enums\WorkOrderStatus;
use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_ops_work_orders';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'work_order_number',
        'ticket_id',
        'account_id',
        'title',
        'description',
        'status',
        'scheduled_date',
        'technician_id',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'scheduled_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}