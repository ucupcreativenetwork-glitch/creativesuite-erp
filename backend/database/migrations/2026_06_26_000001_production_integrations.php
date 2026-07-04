<?php

use App\Modules\Finance\Data\DefaultCoaTemplate;
use App\Modules\Finance\Enums\AccountMappingKey;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Finance\Models\ChartOfAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cs_core_permissions')) {
            (new \Database\Seeders\PermissionSeeder)->run();
        }

        if (Schema::hasTable('cs_fin_invoices') && ! Schema::hasColumn('cs_fin_invoices', 'purchase_order_id')) {
            Schema::table('cs_fin_invoices', function (Blueprint $table): void {
                $table->unsignedBigInteger('purchase_order_id')->nullable()->after('project_id');
            });
        }

        if (Schema::hasTable('cs_pur_orders') && ! Schema::hasColumn('cs_pur_orders', 'invoice_id')) {
            Schema::table('cs_pur_orders', function (Blueprint $table): void {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('total_amount');
            });
        }

        if (! Schema::hasTable('cs_fin_chart_of_accounts')) {
            return;
        }

        $inventoryAccounts = collect(DefaultCoaTemplate::accounts())
            ->filter(fn (array $item) => ! empty($item['mapping'])
                && in_array($item['mapping'], [
                    AccountMappingKey::InventoryAccount,
                    AccountMappingKey::CogsAccount,
                ], true))
            ->values()
            ->all();

        if ($inventoryAccounts === []) {
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

            foreach ($inventoryAccounts as $item) {
                if (isset($codeMap[$item['code']])) {
                    AccountMapping::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $row->tenant_id,
                            'company_id' => $row->company_id,
                            'mapping_key' => $item['mapping']->value,
                        ],
                        ['account_id' => $codeMap[$item['code']]],
                    );

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
        if (Schema::hasTable('cs_pur_orders') && Schema::hasColumn('cs_pur_orders', 'invoice_id')) {
            Schema::table('cs_pur_orders', function (Blueprint $table): void {
                $table->dropColumn('invoice_id');
            });
        }

        if (Schema::hasTable('cs_fin_invoices') && Schema::hasColumn('cs_fin_invoices', 'purchase_order_id')) {
            Schema::table('cs_fin_invoices', function (Blueprint $table): void {
                $table->dropColumn('purchase_order_id');
            });
        }
    }
};