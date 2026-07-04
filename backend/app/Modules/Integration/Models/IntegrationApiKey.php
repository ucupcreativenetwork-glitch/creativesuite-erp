<?php

namespace App\Modules\Integration\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationApiKey extends Model
{
    use BelongsToTenant;

    protected $table = 'cs_int_api_keys';

    protected $fillable = [
        'public_id', 'tenant_id', 'company_id', 'name', 'key_prefix', 'key_hash',
        'scopes', 'is_active', 'expires_at', 'last_used_at', 'created_by',
    ];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}