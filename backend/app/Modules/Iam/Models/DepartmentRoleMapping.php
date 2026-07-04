<?php

namespace App\Modules\Iam\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentRoleMapping extends Model
{
    use BelongsToTenant;

    protected $table = 'cs_core_department_role_mappings';

    protected $fillable = [
        'tenant_id', 'company_id', 'department_id', 'role_id', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}