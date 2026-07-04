<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tenant;
use App\Modules\Iam\Config\IamRoleCatalog;
use App\Modules\Iam\Models\Department;
use App\Modules\Iam\Models\DepartmentRoleMapping;
use Illuminate\Support\Str;

class IamBootstrapService
{
    public function __construct(protected WorkflowManagementService $workflowService) {}

    public function bootstrapTenant(Tenant $tenant, Company $company, ?int $createdBy = null): void
    {
        $roles = $this->seedRoles($tenant);
        $departments = $this->seedDepartments($tenant, $company);
        $this->seedDepartmentRoleMappings($tenant, $company, $departments, $roles);
        $this->workflowService->ensureAllWorkflows($tenant->id, $company->id, $createdBy);
        $this->grantOwnerIamPermissions($tenant);
        $this->grantGmIamPermissions($tenant);
    }

    protected function seedRoles(Tenant $tenant): array
    {
        $map = [];
        foreach (IamRoleCatalog::roles() as $key => $def) {
            $role = Role::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $def['code']],
                ['name' => $def['name'], 'is_system' => true, 'is_active' => true],
            );
            if (! empty($def['perms'])) {
                $permIds = Permission::query()->whereIn('code', $def['perms'])->pluck('id');
                $role->permissions()->syncWithoutDetaching($permIds);
            }
            $map[$key] = $role;
        }

        return $map;
    }

    protected function seedDepartments(Tenant $tenant, Company $company): array
    {
        $map = [];
        foreach (IamRoleCatalog::DEPARTMENTS as $code => $name) {
            $map[$code] = Department::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => $code],
                ['public_id' => (string) Str::uuid(), 'name' => $name, 'is_active' => true],
            );
        }

        return $map;
    }

    protected function seedDepartmentRoleMappings(Tenant $tenant, Company $company, array $departments, array $roles): void
    {
        $expectedPairs = [];

        foreach (IamRoleCatalog::roles() as $key => $def) {
            if (empty($def['dept']) || str_starts_with($def['code'], 'HEAD_')) {
                continue;
            }
            $dept = $departments[$def['dept']] ?? null;
            $role = $roles[$key] ?? Role::query()->where('tenant_id', $tenant->id)->where('code', $def['code'])->first();
            if ($dept && $role) {
                $expectedPairs[] = ['department_id' => $dept->id, 'role_id' => $role->id];
                DepartmentRoleMapping::query()->updateOrCreate(
                    ['department_id' => $dept->id, 'role_id' => $role->id],
                    ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'is_active' => true],
                );
            }
        }

        // Deactivate stale mappings
        $validRoleIds = collect($expectedPairs)->pluck('role_id')->unique();
        $validDeptIds = collect($expectedPairs)->pluck('department_id')->unique();

        if ($validDeptIds->isNotEmpty()) {
            DepartmentRoleMapping::query()
                ->where('tenant_id', $tenant->id)
                ->where('company_id', $company->id)
                ->whereIn('department_id', $validDeptIds)
                ->whereNotIn('role_id', $validRoleIds)
                ->update(['is_active' => false]);
        }
    }

    protected function grantOwnerIamPermissions(Tenant $tenant): void
    {
        $owner = Role::query()->where('tenant_id', $tenant->id)->where('code', 'TENANT_OWNER')->first();
        if (! $owner) {
            return;
        }

        $iamPerms = Permission::query()->where('code', 'like', 'iam.%')->pluck('id');
        $owner->permissions()->syncWithoutDetaching($iamPerms);
    }

    protected function grantGmIamPermissions(Tenant $tenant): void
    {
        $gm = Role::query()->where('tenant_id', $tenant->id)->where('code', 'GENERAL_MANAGER')->first();
        if (! $gm) {
            return;
        }

        $perms = Permission::query()->whereIn('code', [
            'iam.request.read.all', 'iam.request.approve', 'iam.request.reject',
            'iam.request.request_revision', 'iam.audit.read',
        ])->pluck('id');
        $gm->permissions()->syncWithoutDetaching($perms);
    }
}