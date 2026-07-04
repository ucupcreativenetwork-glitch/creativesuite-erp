<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\EntityType;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'cs_core_companies';

    protected $fillable = [
        'tenant_id',
        'public_id',
        'legal_name',
        'trade_name',
        'entity_type',
        'npwp',
        'nitku',
        'address',
        'city',
        'province',
        'postal_code',
        'phone',
        'email',
        'logo_url',
        'is_pkp',
        'is_active',
        'settings',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entity_type' => EntityType::class,
            'is_pkp' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
            'npwp' => 'encrypted',
            'nitku' => 'encrypted',
            'phone' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'default_company_id');
    }

    /** Absolute URL for logo — aman dipakai frontend di origin berbeda. */
    public function resolvedLogoUrl(): ?string
    {
        $logo = $this->logo_url;

        if (! $logo) {
            return null;
        }

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return $logo;
        }

        $path = match (true) {
            str_starts_with($logo, '/storage/') => $logo,
            str_starts_with($logo, 'storage/') => '/'.$logo,
            default => '/storage/'.ltrim($logo, '/'),
        };

        return url($path);
    }
}