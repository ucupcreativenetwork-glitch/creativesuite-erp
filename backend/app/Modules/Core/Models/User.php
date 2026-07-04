<?php

namespace App\Modules\Core\Models;

use App\Support\Tenant\BelongsToTenant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use BelongsToTenant, HasFactory, Notifiable, SoftDeletes;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $table = 'cs_core_users';

    protected $fillable = [
        'tenant_id',
        'public_id',
        'email',
        'password',
        'full_name',
        'phone',
        'avatar_url',
        'default_company_id',
        'default_branch_id',
        'department_id',
        'position',
        'direct_manager_id',
        'provisioning_source',
        'provisioned_from_request_id',
        'must_change_password',
        'activated_at',
        'is_active',
        'account_status',
        'failed_login_attempts',
        'locked_until',
        'is_platform_admin',
        'mfa_enabled',
        'mfa_secret',
        'mfa_recovery_codes',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
            'mfa_enabled' => 'boolean',
            'phone' => 'encrypted',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'activated_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'public_id' => $this->public_id,
            'email' => $this->email,
        ];
    }

    public function defaultCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'default_company_id');
    }

    public function defaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'cs_core_user_roles')
            ->withPivot(['tenant_id', 'branch_id'])
            ->withTimestamps();
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'cs_core_user_company_access')
            ->withPivot(['is_default'])
            ->withTimestamps();
    }

    public function companyAccess(): HasMany
    {
        return $this->hasMany(UserCompanyAccess::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Iam\Models\Department::class);
    }

    public function directManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'direct_manager_id');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isTenantAdministrator()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('code', $permission))
            ->exists();
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isTenantAdministrator()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function isTenantAdministrator(): bool
    {
        return $this->roles()->where('code', 'TENANT_OWNER')->exists();
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }
}