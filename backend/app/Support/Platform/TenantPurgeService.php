<?php

namespace App\Support\Platform;

use App\Modules\Core\Models\Tenant;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantPurgeService
{
    public function purgeBySlug(string $slug): array
    {
        $protected = (string) config('platform.tenant_slug', 'platform');
        if ($slug === $protected) {
            throw new ApiException('Tenant sistem platform tidak dapat dihapus.', 422, 'PROTECTED_TENANT');
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();
        if (! $tenant) {
            throw new ApiException('Tenant tidak ditemukan.', 404, 'TENANT_NOT_FOUND');
        }

        return $this->purge($tenant);
    }

    public function purge(Tenant $tenant): array
    {
        $protected = (string) config('platform.tenant_slug', 'platform');
        if ($tenant->slug === $protected) {
            throw new ApiException('Tenant sistem platform tidak dapat dihapus.', 422, 'PROTECTED_TENANT');
        }

        $tenantId = $tenant->id;
        $companyIds = DB::table('cs_core_companies')->where('tenant_id', $tenantId)->pluck('id')->all();
        $deleted = [];

        DB::transaction(function () use ($tenantId, $companyIds, &$deleted): void {
            $this->deleteTaxData($tenantId, $companyIds, $deleted);
            $this->deleteFinanceData($tenantId, $companyIds, $deleted);
            $this->deleteBusinessData($tenantId, $companyIds, $deleted);
            $this->deleteIamData($tenantId, $deleted);
            $this->deleteCoreData($tenantId, $companyIds, $deleted);

            DB::table('cs_platform_tenants')->where('id', $tenantId)->delete();
            $deleted['cs_platform_tenants'] = 1;
        });

        return [
            'tenant_slug' => $tenant->slug,
            'tenant_name' => $tenant->name,
            'deleted_counts' => $deleted,
        ];
    }

    protected function deleteTaxData(int $tenantId, array $companyIds, array &$deleted): void
    {
        if (Schema::hasTable('cs_tax_efaktur_documents')) {
            $ppnIds = $this->ids('cs_tax_ppn_transactions', $tenantId, $companyIds);
            if ($ppnIds) {
                $deleted['cs_tax_efaktur_documents'] = DB::table('cs_tax_efaktur_documents')
                    ->whereIn('ppn_transaction_id', $ppnIds)->delete();
            }
        }

        if (Schema::hasTable('cs_tax_ebupot_documents')) {
            $pphIds = $this->ids('cs_tax_pph23_transactions', $tenantId, $companyIds);
            if ($pphIds) {
                $deleted['cs_tax_ebupot_documents'] = DB::table('cs_tax_ebupot_documents')
                    ->whereIn('pph23_transaction_id', $pphIds)->delete();
            }
        }

        $deleted['cs_tax_ppn_transactions'] = $this->deleteScoped('cs_tax_ppn_transactions', $tenantId, $companyIds);
        $deleted['cs_tax_pph23_transactions'] = $this->deleteScoped('cs_tax_pph23_transactions', $tenantId, $companyIds);
        $deleted['cs_tax_spt_masa_ppn'] = $this->deleteScoped('cs_tax_spt_masa_ppn', $tenantId, $companyIds);
    }

    protected function deleteFinanceData(int $tenantId, array $companyIds, array &$deleted): void
    {
        $deleted['cs_fin_bank_statement_lines'] = $this->deleteScoped('cs_fin_bank_statement_lines', $tenantId, $companyIds);
        $deleted['cs_fin_payments'] = $this->deleteScoped('cs_fin_payments', $tenantId, $companyIds);

        $invoiceIds = $this->ids('cs_fin_invoices', $tenantId, $companyIds);
        if ($invoiceIds) {
            $deleted['cs_fin_invoice_lines'] = DB::table('cs_fin_invoice_lines')
                ->whereIn('invoice_id', $invoiceIds)->delete();
        }
        $deleted['cs_fin_invoices'] = $this->deleteScoped('cs_fin_invoices', $tenantId, $companyIds);

        $journalIds = $this->ids('cs_fin_journal_entries', $tenantId, $companyIds);
        if ($journalIds) {
            $deleted['cs_fin_journal_entry_lines'] = DB::table('cs_fin_journal_entry_lines')
                ->whereIn('journal_entry_id', $journalIds)->delete();
        }
        $deleted['cs_fin_period_account_balances'] = $this->deleteScoped('cs_fin_period_account_balances', $tenantId, $companyIds);
        $deleted['cs_fin_journal_entries'] = $this->deleteScoped('cs_fin_journal_entries', $tenantId, $companyIds);
        $deleted['cs_fin_account_mappings'] = $this->deleteScoped('cs_fin_account_mappings', $tenantId, $companyIds);
        $deleted['cs_fin_fiscal_periods'] = $this->deleteScoped('cs_fin_fiscal_periods', $tenantId, $companyIds);
        $deleted['cs_fin_chart_of_accounts'] = $this->deleteScoped('cs_fin_chart_of_accounts', $tenantId, $companyIds);
    }

    protected function deleteBusinessData(int $tenantId, array $companyIds, array &$deleted): void
    {
        $projectIds = $this->ids('cs_prj_projects', $tenantId, $companyIds);
        if ($projectIds) {
            $deleted['cs_prj_time_entries'] = DB::table('cs_prj_time_entries')->whereIn('project_id', $projectIds)->delete();
            $deleted['cs_prj_milestones'] = DB::table('cs_prj_milestones')->whereIn('project_id', $projectIds)->delete();
        }
        $deleted['cs_prj_projects'] = $this->deleteScoped('cs_prj_projects', $tenantId, $companyIds);

        $runIds = $this->ids('cs_hr_payroll_runs', $tenantId, $companyIds);
        if ($runIds) {
            $deleted['cs_hr_payroll_lines'] = DB::table('cs_hr_payroll_lines')->whereIn('payroll_run_id', $runIds)->delete();
        }
        $deleted['cs_hr_payroll_runs'] = $this->deleteScoped('cs_hr_payroll_runs', $tenantId, $companyIds);
        $deleted['cs_hr_leave_requests'] = $this->deleteScoped('cs_hr_leave_requests', $tenantId, $companyIds);
        $deleted['cs_hr_attendance_records'] = $this->deleteScoped('cs_hr_attendance_records', $tenantId, $companyIds);

        $poIds = $this->ids('cs_pur_orders', $tenantId, $companyIds);
        if ($poIds) {
            $deleted['cs_pur_order_lines'] = DB::table('cs_pur_order_lines')->whereIn('purchase_order_id', $poIds)->delete();
        }
        $deleted['cs_pur_orders'] = $this->deleteScoped('cs_pur_orders', $tenantId, $companyIds);

        $itemIds = $this->ids('cs_inv_items', $tenantId, $companyIds);
        $deleted['cs_inv_stock_movements'] = $this->deleteScoped('cs_inv_stock_movements', $tenantId, $companyIds);
        if ($itemIds) {
            $deleted['cs_inv_stock_balances'] = DB::table('cs_inv_stock_balances')->whereIn('item_id', $itemIds)->delete();
        }
        $deleted['cs_inv_items'] = $this->deleteScoped('cs_inv_items', $tenantId, $companyIds);
        $deleted['cs_inv_warehouses'] = $this->deleteScoped('cs_inv_warehouses', $tenantId, $companyIds);

        $deleted['cs_ops_work_orders'] = $this->deleteScoped('cs_ops_work_orders', $tenantId, $companyIds);
        $deleted['cs_ops_tickets'] = $this->deleteScoped('cs_ops_tickets', $tenantId, $companyIds);

        $quotationIds = $this->ids('cs_sales_quotations', $tenantId, $companyIds);
        if ($quotationIds) {
            $deleted['cs_sales_quotation_lines'] = DB::table('cs_sales_quotation_lines')
                ->whereIn('quotation_id', $quotationIds)->delete();
        }
        $deleted['cs_sales_quotations'] = $this->deleteScoped('cs_sales_quotations', $tenantId, $companyIds);

        $accountIds = $this->ids('cs_crm_accounts', $tenantId, $companyIds);
        if ($accountIds) {
            $deleted['cs_crm_contacts'] = DB::table('cs_crm_contacts')->whereIn('account_id', $accountIds)->delete();
        }
        $deleted['cs_crm_accounts'] = $this->deleteScoped('cs_crm_accounts', $tenantId, $companyIds);
        $deleted['cs_hr_employees'] = $this->deleteScoped('cs_hr_employees', $tenantId, $companyIds);
    }

    protected function deleteIamData(int $tenantId, array &$deleted): void
    {
        $deleted['cs_core_approval_history'] = $this->deleteByTenant('cs_core_approval_history', $tenantId);
        $deleted['cs_core_user_creation_requests'] = $this->deleteByTenant('cs_core_user_creation_requests', $tenantId);
        $deleted['cs_core_audit_logs'] = $this->deleteByTenant('cs_core_audit_logs', $tenantId);
        $deleted['cs_core_notifications'] = $this->deleteByTenant('cs_core_notifications', $tenantId);
        $deleted['cs_core_push_devices'] = $this->deleteByTenant('cs_core_push_devices', $tenantId);
        $deleted['cs_core_user_activation_tokens'] = $this->deleteByTenant('cs_core_user_activation_tokens', $tenantId);
        $deleted['cs_core_user_verification_otps'] = $this->deleteByTenant('cs_core_user_verification_otps', $tenantId);

        $workflowIds = DB::table('cs_core_approval_workflow_configs')
            ->where('tenant_id', $tenantId)->pluck('id')->all();
        if ($workflowIds) {
            $deleted['cs_core_approval_workflow_steps'] = DB::table('cs_core_approval_workflow_steps')
                ->whereIn('workflow_config_id', $workflowIds)->delete();
        }
        $deleted['cs_core_approval_workflow_configs'] = $this->deleteByTenant('cs_core_approval_workflow_configs', $tenantId);
        $deleted['cs_core_department_role_mappings'] = $this->deleteByTenant('cs_core_department_role_mappings', $tenantId);
        $deleted['cs_core_departments'] = $this->deleteByTenant('cs_core_departments', $tenantId);
    }

    protected function deleteCoreData(int $tenantId, array $companyIds, array &$deleted): void
    {
        $userIds = DB::table('cs_core_users')->where('tenant_id', $tenantId)->pluck('id')->all();
        if ($userIds) {
            $deleted['cs_core_user_roles'] = DB::table('cs_core_user_roles')->whereIn('user_id', $userIds)->delete();
        }

        if (Schema::hasColumn('cs_core_user_company_access', 'tenant_id')) {
            $deleted['cs_core_user_company_access'] = $this->deleteByTenant('cs_core_user_company_access', $tenantId);
        } elseif ($companyIds) {
            $deleted['cs_core_user_company_access'] = DB::table('cs_core_user_company_access')
                ->whereIn('company_id', $companyIds)->delete();
        }

        $roleIds = DB::table('cs_core_roles')->where('tenant_id', $tenantId)->pluck('id')->all();
        if ($roleIds) {
            $deleted['cs_core_role_permissions'] = DB::table('cs_core_role_permissions')->whereIn('role_id', $roleIds)->delete();
        }

        $deleted['cs_core_users'] = $this->deleteByTenant('cs_core_users', $tenantId);
        $deleted['cs_core_roles'] = $this->deleteByTenant('cs_core_roles', $tenantId);
        $deleted['cs_core_branches'] = $companyIds
            ? DB::table('cs_core_branches')->whereIn('company_id', $companyIds)->delete()
            : 0;
        $deleted['cs_core_companies'] = $this->deleteByTenant('cs_core_companies', $tenantId);
    }

    protected function deleteScoped(string $table, int $tenantId, array $companyIds): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        if (Schema::hasColumn($table, 'tenant_id')) {
            return (int) DB::table($table)->where('tenant_id', $tenantId)->delete();
        }

        if ($companyIds && Schema::hasColumn($table, 'company_id')) {
            return (int) DB::table($table)->whereIn('company_id', $companyIds)->delete();
        }

        return 0;
    }

    protected function deleteByTenant(string $table, int $tenantId): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            return 0;
        }

        return (int) DB::table($table)->where('tenant_id', $tenantId)->delete();
    }

    protected function ids(string $table, int $tenantId, array $companyIds): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $query = DB::table($table);
        if (Schema::hasColumn($table, 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        } elseif ($companyIds && Schema::hasColumn($table, 'company_id')) {
            $query->whereIn('company_id', $companyIds);
        } else {
            return [];
        }

        return $query->pluck('id')->all();
    }
}