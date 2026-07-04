<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\Role;
use App\Modules\Iam\Models\Department;
use App\Modules\Iam\Models\DepartmentRoleMapping;
use App\Support\Exceptions\ApiException;

class DepartmentRoleGuard
{
    /** @var list<string> */
    public const FORBIDDEN_ROLE_CODES = [
        'TENANT_OWNER', 'COMPANY_OWNER', 'DIRECTOR', 'GENERAL_MANAGER',
    ];

    public function assertRoleAllowedForDepartment(int $departmentId, int $roleId, int $tenantId): Role
    {
        $role = Role::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $roleId)
            ->where('is_active', true)
            ->firstOrFail();

        if (in_array($role->code, self::FORBIDDEN_ROLE_CODES, true)) {
            throw new ApiException('Role ini tidak boleh diajukan oleh Head Divisi.', 422, 'ROLE_NOT_ALLOWED');
        }

        $allowed = DepartmentRoleMapping::query()
            ->where('department_id', $departmentId)
            ->where('role_id', $roleId)
            ->where('is_active', true)
            ->exists();

        if (! $allowed) {
            throw new ApiException('Role tidak diizinkan untuk departemen ini.', 422, 'ROLE_DEPARTMENT_MISMATCH');
        }

        return $role;
    }

    public function resolveDepartmentForHead(int $userId, int $companyId): ?Department
    {
        return Department::query()
            ->where('company_id', $companyId)
            ->where('head_user_id', $userId)
            ->where('is_active', true)
            ->first();
    }
}