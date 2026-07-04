<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\Role;
use App\Modules\Iam\Config\IamRoleCatalog;
use App\Modules\Iam\Models\Department;
use App\Modules\Iam\Models\DepartmentRoleMapping;
use Illuminate\Support\Facades\DB;

class DepartmentRoleMappingService
{
    public function listForCompany(int $tenantId, int $companyId): array
    {
        $departments = Department::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with(['allowedRoles' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        $assignableRoles = Role::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotIn('code', DepartmentRoleGuard::FORBIDDEN_ROLE_CODES)
            ->whereNotIn('code', array_map(fn ($c) => IamRoleCatalog::headRoleCodeForDepartment($c), array_keys(IamRoleCatalog::DEPARTMENTS)))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return [
            'departments' => $departments->map(fn ($d) => [
                'id' => $d->public_id,
                'code' => $d->code,
                'name' => $d->name,
                'role_ids' => $d->allowedRoles->pluck('id')->values(),
            ]),
            'assignable_roles' => $assignableRoles,
        ];
    }

    public function sync(int $tenantId, int $companyId, string $departmentPublicId, array $roleIds): Department
    {
        $department = Department::query()
            ->where('company_id', $companyId)
            ->where('public_id', $departmentPublicId)
            ->firstOrFail();

        $validRoleIds = Role::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $roleIds)
            ->whereNotIn('code', DepartmentRoleGuard::FORBIDDEN_ROLE_CODES)
            ->pluck('id')
            ->all();

        return DB::transaction(function () use ($tenantId, $companyId, $department, $validRoleIds) {
            DepartmentRoleMapping::query()
                ->where('department_id', $department->id)
                ->update(['is_active' => false]);

            foreach ($validRoleIds as $roleId) {
                DepartmentRoleMapping::query()->updateOrCreate(
                    ['department_id' => $department->id, 'role_id' => $roleId],
                    ['tenant_id' => $tenantId, 'company_id' => $companyId, 'is_active' => true],
                );
            }

            return $department->fresh('allowedRoles');
        });
    }
}