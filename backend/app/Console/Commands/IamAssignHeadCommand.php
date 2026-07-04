<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Iam\Config\IamRoleCatalog;
use App\Modules\Iam\Models\Department;
use App\Support\Tenant\TenantManager;
use Illuminate\Console\Command;

class IamAssignHeadCommand extends Command
{
    protected $signature = 'iam:assign-head {tenant_slug} {email} {dept_code}';

    protected $description = 'Assign head-of-department role and link user as department head';

    public function handle(TenantManager $tenantManager): int
    {
        $tenant = \App\Modules\Core\Models\Tenant::query()
            ->where('slug', $this->argument('tenant_slug'))
            ->firstOrFail();

        $tenantManager->set($tenant);

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $this->argument('email'))
            ->firstOrFail();

        $deptCode = strtoupper($this->argument('dept_code'));

        if (! isset(IamRoleCatalog::DEPARTMENTS[$deptCode])) {
            $this->error('Invalid dept_code. Valid: '.implode(', ', array_keys(IamRoleCatalog::DEPARTMENTS)));

            return self::FAILURE;
        }

        $department = Department::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', $deptCode)
            ->firstOrFail();

        $headRoleCode = IamRoleCatalog::headRoleCodeForDepartment($deptCode);

        $role = Role::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', $headRoleCode)
            ->firstOrFail();

        $user->roles()->syncWithoutDetaching([
            $role->id => ['tenant_id' => $tenant->id, 'branch_id' => null],
        ]);

        $department->update(['head_user_id' => $user->id]);

        $this->info("Assigned {$headRoleCode} to {$user->email} as head of {$department->name}");

        return self::SUCCESS;
    }
}