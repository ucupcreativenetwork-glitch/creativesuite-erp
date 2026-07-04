<?php

namespace App\Modules\Core\Models;

use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCompanyAccess extends Model
{
    use BelongsToTenant;

    protected $table = 'cs_core_user_company_access';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'company_id',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}