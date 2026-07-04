<?php

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Finance\Data\DefaultCoaTemplate;
use App\Modules\Finance\Enums\AccountMappingKey;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Finance\Models\ChartOfAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('cs_core_permissions')) {
            (new \Database\Seeders\PermissionSeeder)->run();

            if (\Illuminate\Support\Facades\Schema::hasTable('cs_core_roles')) {
                $permIds = Permission::query()
                    ->whereIn('code', [
                        'prj.project.read',
                        'prj.project.create',
                        'prj.project.update',
                        'prj.project.delete',
                    ])
                    ->pluck('id');

                Role::query()
                    ->where('code', 'TENANT_OWNER')
                    ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($permIds));
            }
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('cs_fin_chart_of_accounts')) {
            return;
        }

        $payrollAccounts = collect(DefaultCoaTemplate::accounts())
            ->filter(fn (array $item) => ! empty($item['mapping'])
                && in_array($item['mapping'], [
                    AccountMappingKey::SalaryPayableAccount,
                    AccountMappingKey::Pph21PayableAccount,
                    AccountMappingKey::BpjsPayableAccount,
                ], true))
            ->values()
            ->all();

        if ($payrollAccounts === []) {
            return;
        }

        $companies = ChartOfAccount::withoutGlobalScopes()
            ->select('tenant_id', 'company_id')
            ->distinct()
            ->get();

        foreach ($companies as $row) {
            $codeMap = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $row->tenant_id)
                ->where('company_id', $row->company_id)
                ->pluck('id', 'code')
                ->all();

            foreach ($payrollAccounts as $item) {
                if (isset($codeMap[$item['code']])) {
                    continue;
                }

                $parentId = isset($item['parent_code'], $codeMap[$item['parent_code']])
                    ? $codeMap[$item['parent_code']]
                    : null;

                $account = ChartOfAccount::withoutGlobalScopes()->create([
                    'tenant_id' => $row->tenant_id,
                    'company_id' => $row->company_id,
                    'public_id' => (string) Str::uuid(),
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'account_type' => $item['account_type'],
                    'parent_id' => $parentId,
                    'normal_balance' => $item['normal_balance'],
                    'is_postable' => $item['is_postable'],
                    'is_active' => true,
                ]);

                $codeMap[$item['code']] = $account->id;

                AccountMapping::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $row->tenant_id,
                        'company_id' => $row->company_id,
                        'mapping_key' => $item['mapping']->value,
                    ],
                    ['account_id' => $account->id],
                );
            }
        }
    }

    public function down(): void
    {
        //
    }
};