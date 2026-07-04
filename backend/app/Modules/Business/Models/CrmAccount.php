<?php

namespace App\Modules\Business\Models;

use App\Modules\Business\Enums\AccountStatus;
use App\Modules\Business\Enums\AccountType;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmAccount extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_crm_accounts';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'account_code',
        'name',
        'account_type',
        'status',
        'email',
        'phone',
        'whatsapp',
        'npwp',
        'address',
        'city',
        'credit_limit',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'status' => AccountStatus::class,
            'credit_limit' => 'decimal:2',
            'npwp' => 'encrypted',
            'phone' => 'encrypted',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'account_id');
    }
}