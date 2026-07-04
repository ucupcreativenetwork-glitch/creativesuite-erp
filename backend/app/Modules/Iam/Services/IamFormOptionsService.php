<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Iam\Config\IamRoleCatalog;
use App\Modules\Iam\Models\Department;
use App\Modules\Iam\Resources\DepartmentResource;
use App\Support\Exceptions\ApiException;

class IamFormOptionsService
{
    public function __construct(protected DepartmentRoleGuard $roleGuard) {}

    public function resolve(User $actor, ?string $departmentPublicId = null): array
    {
        $companyId = $actor->default_company_id;
        $headDept = $this->roleGuard->resolveDepartmentForHead($actor->id, $companyId);
        $isOwner = $actor->roles()->where('code', 'TENANT_OWNER')->exists();
        $canSelectDepartment = $isOwner || ($actor->hasPermission('iam.department.read') && ! $headDept);

        $department = $this->resolveTargetDepartment(
            $companyId,
            $departmentPublicId,
            $headDept,
            $canSelectDepartment,
            $isOwner,
        );

        $department->load('allowedRoles');

        $allDepartments = Department::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with('allowedRoles')
            ->orderBy('name')
            ->get();

        $branches = Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByDesc('is_head_office')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_head_office']);

        $allowedRoles = $department->allowedRoles->map(fn ($r) => [
            'id' => $r->id,
            'code' => $r->code,
            'name' => $r->name,
        ])->values();

        $directManagers = $this->resolveDirectManagers($actor, $department, $headDept);

        return [
            'can_select_department' => $canSelectDepartment,
            'is_tenant_owner' => $isOwner,
            'department' => (new DepartmentResource($department))->resolve(),
            'departments' => DepartmentResource::collection(
                $canSelectDepartment ? $allDepartments : collect([$department]),
            )->resolve(),
            'allowed_roles' => $allowedRoles,
            'positions' => IamRoleCatalog::positionsByDepartment()[$department->code] ?? ['Staff', 'Supervisor'],
            'branches' => $branches,
            'direct_managers' => $directManagers,
        ];
    }

    protected function resolveTargetDepartment(
        int $companyId,
        ?string $departmentPublicId,
        ?Department $headDept,
        bool $canSelectDepartment,
        bool $isOwner,
    ): Department {
        if ($departmentPublicId) {
            $department = Department::query()
                ->where('company_id', $companyId)
                ->where('public_id', $departmentPublicId)
                ->where('is_active', true)
                ->firstOrFail();

            if ($headDept && $headDept->id !== $department->id && ! $canSelectDepartment) {
                throw new ApiException('Anda hanya dapat membuat request untuk departemen Anda.', 403, 'DEPARTMENT_NOT_ALLOWED');
            }

            return $department;
        }

        if ($headDept) {
            return $headDept;
        }

        if ($canSelectDepartment) {
            return Department::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->first()
                ?? throw new ApiException('Departemen tidak ditemukan. Jalankan IAM bootstrap.', 404, 'NO_DEPARTMENT');
        }

        throw new ApiException(
            'Anda bukan Head Divisi. Hubungi admin untuk assign Head Divisi (iam:assign-head).',
            403,
            'NOT_DEPARTMENT_HEAD',
        );
    }

    /** @return list<array{id: string, internal_id: int, full_name: string, email: string}> */
    protected function resolveDirectManagers(User $actor, Department $department, ?Department $headDept): array
    {
        $managers = collect();

        if ($department->head_user_id) {
            $head = User::query()->find($department->head_user_id);
            if ($head) {
                $managers->push([
                    'id' => $head->public_id,
                    'internal_id' => $head->id,
                    'full_name' => $head->full_name,
                    'email' => $head->email,
                ]);
            }
        }

        if ($headDept && $headDept->head_user_id === $actor->id) {
            $managers->push([
                'id' => $actor->public_id,
                'internal_id' => $actor->id,
                'full_name' => $actor->full_name,
                'email' => $actor->email,
            ]);
        }

        return $managers->unique('id')->values()->all();
    }
}