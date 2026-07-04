<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Finance\Services\CoaSetupService;
use Illuminate\Database\Seeder;

class FinanceSetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $coaSetup = app(CoaSetupService::class);

        Company::query()->each(function (Company $company) use ($coaSetup): void {
            $coaSetup->setupForCompany($company->tenant_id, $company->id);
        });

        Role::query()->where('code', 'TENANT_OWNER')->each(function (Role $role): void {
            $role->permissions()->sync(Permission::query()->pluck('id'));
        });

        // Payroll disbursement demo: after posting a payroll run, call
        // PayrollService::disbursePayroll($user, $runPublicId, $bankAccountId)
        // to record Dr Utang Gaji / Cr Bank (idempotent per run).
    }
}