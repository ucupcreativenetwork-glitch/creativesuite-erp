<?php

namespace App\Modules\Business\Models;

use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmContact extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'cs_crm_contacts';

    protected $fillable = [
        'tenant_id',
        'account_id',
        'public_id',
        'full_name',
        'job_title',
        'email',
        'phone',
        'whatsapp',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }
}