<?php

namespace App\Modules\Iam\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'cs_core_departments';

    protected $fillable = [
        'tenant_id', 'company_id', 'public_id', 'code', 'name',
        'parent_department_id', 'head_user_id', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function headUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function allowedRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'cs_core_department_role_mappings', 'department_id', 'role_id')
            ->withPivot(['is_active'])
            ->wherePivot('is_active', true);
    }

    public function roleMappings(): HasMany
    {
        return $this->hasMany(DepartmentRoleMapping::class, 'department_id');
    }
}