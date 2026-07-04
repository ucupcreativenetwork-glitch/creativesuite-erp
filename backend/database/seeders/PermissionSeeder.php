<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Core
            ['module' => 'core', 'action' => 'create', 'code' => 'core.user.create', 'description' => 'Create users'],
            ['module' => 'core', 'action' => 'read', 'code' => 'core.user.read', 'description' => 'View users'],
            ['module' => 'core', 'action' => 'update', 'code' => 'core.user.update', 'description' => 'Update users'],
            ['module' => 'core', 'action' => 'delete', 'code' => 'core.user.delete', 'description' => 'Delete users'],
            ['module' => 'core', 'action' => 'create', 'code' => 'core.company.create', 'description' => 'Create companies'],
            ['module' => 'core', 'action' => 'read', 'code' => 'core.company.read', 'description' => 'View companies'],
            ['module' => 'core', 'action' => 'update', 'code' => 'core.company.update', 'description' => 'Update companies'],
            ['module' => 'core', 'action' => 'read', 'code' => 'core.role.read', 'description' => 'View roles'],
            ['module' => 'core', 'action' => 'create', 'code' => 'core.role.create', 'description' => 'Create roles'],

            // IAM — Delegated User Management
            ['module' => 'iam', 'action' => 'create', 'code' => 'iam.request.create', 'description' => 'Create user requests'],
            ['module' => 'iam', 'action' => 'read', 'code' => 'iam.request.read.own', 'description' => 'View own user requests'],
            ['module' => 'iam', 'action' => 'read', 'code' => 'iam.request.read.all', 'description' => 'View all user requests'],
            ['module' => 'iam', 'action' => 'update', 'code' => 'iam.request.update.own', 'description' => 'Update own user requests'],
            ['module' => 'iam', 'action' => 'cancel', 'code' => 'iam.request.cancel.own', 'description' => 'Cancel own user requests'],
            ['module' => 'iam', 'action' => 'cancel', 'code' => 'iam.request.cancel.any', 'description' => 'Cancel any user request'],
            ['module' => 'iam', 'action' => 'approve', 'code' => 'iam.request.approve', 'description' => 'Approve user requests'],
            ['module' => 'iam', 'action' => 'reject', 'code' => 'iam.request.reject', 'description' => 'Reject user requests'],
            ['module' => 'iam', 'action' => 'revision', 'code' => 'iam.request.request_revision', 'description' => 'Request revision on user requests'],
            ['module' => 'iam', 'action' => 'override', 'code' => 'iam.request.override', 'description' => 'Override approve user requests'],
            ['module' => 'iam', 'action' => 'revoke', 'code' => 'iam.user.revoke', 'description' => 'Revoke provisioned users'],
            ['module' => 'iam', 'action' => 'read', 'code' => 'iam.workflow.read', 'description' => 'View IAM workflows'],
            ['module' => 'iam', 'action' => 'manage', 'code' => 'iam.workflow.manage', 'description' => 'Manage IAM workflows'],
            ['module' => 'iam', 'action' => 'read', 'code' => 'iam.department.read', 'description' => 'View departments'],
            ['module' => 'iam', 'action' => 'manage', 'code' => 'iam.department_role.manage', 'description' => 'Manage department role mappings'],
            ['module' => 'iam', 'action' => 'read', 'code' => 'iam.audit.read', 'description' => 'View IAM audit logs'],

            // CRM
            ['module' => 'crm', 'action' => 'read', 'code' => 'crm.account.read', 'description' => 'View accounts'],
            ['module' => 'crm', 'action' => 'create', 'code' => 'crm.account.create', 'description' => 'Create accounts'],
            ['module' => 'crm', 'action' => 'update', 'code' => 'crm.account.update', 'description' => 'Update accounts'],
            ['module' => 'crm', 'action' => 'delete', 'code' => 'crm.account.delete', 'description' => 'Delete accounts'],
            ['module' => 'crm', 'action' => 'read', 'code' => 'crm.contact.read', 'description' => 'View contacts'],
            ['module' => 'crm', 'action' => 'create', 'code' => 'crm.contact.create', 'description' => 'Create contacts'],

            // Sales
            ['module' => 'sales', 'action' => 'read', 'code' => 'sales.quotation.read', 'description' => 'View quotations'],
            ['module' => 'sales', 'action' => 'create', 'code' => 'sales.quotation.create', 'description' => 'Create quotations'],
            ['module' => 'sales', 'action' => 'update', 'code' => 'sales.quotation.update', 'description' => 'Update quotations'],
            ['module' => 'sales', 'action' => 'delete', 'code' => 'sales.quotation.delete', 'description' => 'Delete quotations'],
            ['module' => 'sales', 'action' => 'send', 'code' => 'sales.quotation.send', 'description' => 'Send quotations'],
            ['module' => 'sales', 'action' => 'accept', 'code' => 'sales.quotation.accept', 'description' => 'Accept quotations'],

            // Ops
            ['module' => 'ops', 'action' => 'read', 'code' => 'ops.ticket.read', 'description' => 'View tickets'],
            ['module' => 'ops', 'action' => 'create', 'code' => 'ops.ticket.create', 'description' => 'Create tickets'],
            ['module' => 'ops', 'action' => 'update', 'code' => 'ops.ticket.update', 'description' => 'Update tickets'],
            ['module' => 'ops', 'action' => 'delete', 'code' => 'ops.ticket.delete', 'description' => 'Delete tickets'],
            ['module' => 'ops', 'action' => 'assign', 'code' => 'ops.ticket.assign', 'description' => 'Assign tickets'],
            ['module' => 'ops', 'action' => 'resolve', 'code' => 'ops.ticket.resolve', 'description' => 'Resolve tickets'],
            ['module' => 'ops', 'action' => 'close', 'code' => 'ops.ticket.close', 'description' => 'Close tickets'],
            ['module' => 'ops', 'action' => 'read', 'code' => 'ops.work_order.read', 'description' => 'View work orders'],
            ['module' => 'ops', 'action' => 'create', 'code' => 'ops.work_order.create', 'description' => 'Create work orders'],
            ['module' => 'ops', 'action' => 'update', 'code' => 'ops.work_order.update', 'description' => 'Update work orders'],
            ['module' => 'ops', 'action' => 'delete', 'code' => 'ops.work_order.delete', 'description' => 'Delete work orders'],
            ['module' => 'ops', 'action' => 'assign', 'code' => 'ops.work_order.assign', 'description' => 'Assign work orders'],
            ['module' => 'ops', 'action' => 'complete', 'code' => 'ops.work_order.complete', 'description' => 'Complete work orders'],

            // Projects
            ['module' => 'prj', 'action' => 'read', 'code' => 'prj.project.read', 'description' => 'View projects'],
            ['module' => 'prj', 'action' => 'create', 'code' => 'prj.project.create', 'description' => 'Create projects'],
            ['module' => 'prj', 'action' => 'update', 'code' => 'prj.project.update', 'description' => 'Update projects'],
            ['module' => 'prj', 'action' => 'delete', 'code' => 'prj.project.delete', 'description' => 'Delete projects'],
            ['module' => 'prj', 'action' => 'read', 'code' => 'prj.timesheet.read', 'description' => 'View project timesheets'],
            ['module' => 'prj', 'action' => 'create', 'code' => 'prj.timesheet.create', 'description' => 'Create timesheet entries'],
            ['module' => 'prj', 'action' => 'update', 'code' => 'prj.timesheet.update', 'description' => 'Update timesheet entries'],
            ['module' => 'prj', 'action' => 'delete', 'code' => 'prj.timesheet.delete', 'description' => 'Delete timesheet entries'],
            ['module' => 'prj', 'action' => 'read', 'code' => 'prj.milestone.read', 'description' => 'View project milestones'],
            ['module' => 'prj', 'action' => 'create', 'code' => 'prj.milestone.create', 'description' => 'Create project milestones'],
            ['module' => 'prj', 'action' => 'update', 'code' => 'prj.milestone.update', 'description' => 'Update project milestones'],
            ['module' => 'prj', 'action' => 'delete', 'code' => 'prj.milestone.delete', 'description' => 'Delete project milestones'],
            ['module' => 'prj', 'action' => 'invoice', 'code' => 'prj.milestone.invoice', 'description' => 'Generate invoice from milestone'],

            // Inventory
            ['module' => 'inv', 'action' => 'read', 'code' => 'inv.item.read', 'description' => 'View inventory items'],
            ['module' => 'inv', 'action' => 'create', 'code' => 'inv.item.create', 'description' => 'Create inventory items'],
            ['module' => 'inv', 'action' => 'update', 'code' => 'inv.item.update', 'description' => 'Update inventory items'],
            ['module' => 'inv', 'action' => 'delete', 'code' => 'inv.item.delete', 'description' => 'Delete inventory items'],
            ['module' => 'inv', 'action' => 'read', 'code' => 'inv.warehouse.read', 'description' => 'View warehouses'],
            ['module' => 'inv', 'action' => 'create', 'code' => 'inv.warehouse.create', 'description' => 'Create warehouses'],
            ['module' => 'inv', 'action' => 'update', 'code' => 'inv.warehouse.update', 'description' => 'Update warehouses'],
            ['module' => 'inv', 'action' => 'delete', 'code' => 'inv.warehouse.delete', 'description' => 'Delete warehouses'],
            ['module' => 'inv', 'action' => 'read', 'code' => 'inv.movement.read', 'description' => 'View stock movements'],
            ['module' => 'inv', 'action' => 'create', 'code' => 'inv.movement.create', 'description' => 'Create stock movements'],
            ['module' => 'inv', 'action' => 'read', 'code' => 'inv.balance.read', 'description' => 'View stock balances'],

            // Purchasing
            ['module' => 'pur', 'action' => 'read', 'code' => 'pur.order.read', 'description' => 'View purchase orders'],
            ['module' => 'pur', 'action' => 'create', 'code' => 'pur.order.create', 'description' => 'Create purchase orders'],
            ['module' => 'pur', 'action' => 'update', 'code' => 'pur.order.update', 'description' => 'Update purchase orders'],
            ['module' => 'pur', 'action' => 'delete', 'code' => 'pur.order.delete', 'description' => 'Delete purchase orders'],
            ['module' => 'pur', 'action' => 'submit', 'code' => 'pur.order.submit', 'description' => 'Submit purchase orders'],
            ['module' => 'pur', 'action' => 'approve', 'code' => 'pur.order.approve', 'description' => 'Approve purchase orders'],
            ['module' => 'pur', 'action' => 'receive', 'code' => 'pur.order.receive', 'description' => 'Receive purchase orders'],

            // HR / Payroll
            ['module' => 'hr', 'action' => 'read', 'code' => 'hr.employee.read', 'description' => 'View employees'],
            ['module' => 'hr', 'action' => 'create', 'code' => 'hr.employee.create', 'description' => 'Create employees'],
            ['module' => 'hr', 'action' => 'update', 'code' => 'hr.employee.update', 'description' => 'Update employees'],
            ['module' => 'hr', 'action' => 'delete', 'code' => 'hr.employee.delete', 'description' => 'Delete employees'],
            ['module' => 'hr', 'action' => 'read', 'code' => 'hr.payroll.read', 'description' => 'View payroll runs'],
            ['module' => 'hr', 'action' => 'create', 'code' => 'hr.payroll.create', 'description' => 'Create payroll runs'],
            ['module' => 'hr', 'action' => 'calculate', 'code' => 'hr.payroll.calculate', 'description' => 'Calculate payroll runs'],
            ['module' => 'hr', 'action' => 'post', 'code' => 'hr.payroll.post', 'description' => 'Post payroll runs'],
            ['module' => 'hr', 'action' => 'read', 'code' => 'hr.attendance.read', 'description' => 'View attendance records'],
            ['module' => 'hr', 'action' => 'clock', 'code' => 'hr.attendance.clock', 'description' => 'Clock in and out'],
            ['module' => 'hr', 'action' => 'manage', 'code' => 'hr.attendance.manage', 'description' => 'Manage all employee attendance'],
            ['module' => 'hr', 'action' => 'read', 'code' => 'hr.attendance.report', 'description' => 'View monthly attendance reports'],
            ['module' => 'hr', 'action' => 'read', 'code' => 'hr.leave.read', 'description' => 'View leave requests'],
            ['module' => 'hr', 'action' => 'create', 'code' => 'hr.leave.create', 'description' => 'Submit leave requests'],
            ['module' => 'hr', 'action' => 'approve', 'code' => 'hr.leave.approve', 'description' => 'Approve or reject leave requests'],
            ['module' => 'hr', 'action' => 'manage', 'code' => 'hr.leave.manage', 'description' => 'Manage all leave requests'],

            // Reports
            ['module' => 'rpt', 'action' => 'read', 'code' => 'rpt.dashboard.read', 'description' => 'View business dashboard'],

            // Integrations
            ['module' => 'int', 'action' => 'read', 'code' => 'int.api_key.read', 'description' => 'View integration API keys'],
            ['module' => 'int', 'action' => 'manage', 'code' => 'int.api_key.manage', 'description' => 'Manage integration API keys'],
            ['module' => 'int', 'action' => 'read', 'code' => 'int.webhook.read', 'description' => 'View webhook endpoints'],
            ['module' => 'int', 'action' => 'manage', 'code' => 'int.webhook.manage', 'description' => 'Manage webhook endpoints'],
            ['module' => 'int', 'action' => 'read', 'code' => 'int.auto_reorder.read', 'description' => 'View auto-reorder rules'],
            ['module' => 'int', 'action' => 'manage', 'code' => 'int.auto_reorder.manage', 'description' => 'Manage auto-reorder rules'],
            ['module' => 'int', 'action' => 'read', 'code' => 'int.connector.read', 'description' => 'View attendance connectors'],
            ['module' => 'int', 'action' => 'manage', 'code' => 'int.connector.manage', 'description' => 'Manage attendance connectors'],
            ['module' => 'int', 'action' => 'run', 'code' => 'int.auto_reorder.run', 'description' => 'Run auto-reorder manually'],

            // Finance
            ['module' => 'fin', 'action' => 'read', 'code' => 'fin.coa.read', 'description' => 'View chart of accounts'],
            ['module' => 'fin', 'action' => 'create', 'code' => 'fin.coa.create', 'description' => 'Create chart of accounts'],
            ['module' => 'fin', 'action' => 'update', 'code' => 'fin.coa.update', 'description' => 'Update chart of accounts'],
            ['module' => 'fin', 'action' => 'read', 'code' => 'fin.journal.read', 'description' => 'View journal entries'],
            ['module' => 'fin', 'action' => 'create', 'code' => 'fin.journal.create', 'description' => 'Create journal entries'],
            ['module' => 'fin', 'action' => 'post', 'code' => 'fin.journal.post', 'description' => 'Post journal entries'],
            ['module' => 'fin', 'action' => 'read', 'code' => 'fin.report.read', 'description' => 'View financial reports'],
            ['module' => 'fin', 'action' => 'read', 'code' => 'fin.bank_recon.read', 'description' => 'View bank reconciliation'],
            ['module' => 'fin', 'action' => 'create', 'code' => 'fin.bank_recon.create', 'description' => 'Create bank statement lines'],
            ['module' => 'fin', 'action' => 'match', 'code' => 'fin.bank_recon.match', 'description' => 'Match bank lines to payments'],
            ['module' => 'fin', 'action' => 'read', 'code' => 'fin.invoice.read', 'description' => 'View invoices'],
            ['module' => 'fin', 'action' => 'create', 'code' => 'fin.invoice.create', 'description' => 'Create invoices'],
            ['module' => 'fin', 'action' => 'update', 'code' => 'fin.invoice.update', 'description' => 'Update draft invoices'],
            ['module' => 'fin', 'action' => 'post', 'code' => 'fin.invoice.post', 'description' => 'Post invoices'],
            ['module' => 'fin', 'action' => 'read', 'code' => 'fin.fiscal_period.read', 'description' => 'View fiscal periods'],
            ['module' => 'fin', 'action' => 'close', 'code' => 'fin.fiscal_period.close', 'description' => 'Close fiscal periods'],
            ['module' => 'fin', 'action' => 'lock', 'code' => 'fin.fiscal_period.lock', 'description' => 'Lock fiscal periods'],
            ['module' => 'fin', 'action' => 'read', 'code' => 'fin.payment.read', 'description' => 'View payments'],
            ['module' => 'fin', 'action' => 'create', 'code' => 'fin.payment.create', 'description' => 'Create payments'],
            ['module' => 'fin', 'action' => 'post', 'code' => 'fin.payment.post', 'description' => 'Post payments'],
            ['module' => 'fin', 'action' => 'read', 'code' => 'fin.tax.ppn.read', 'description' => 'View PPN transactions'],
            ['module' => 'fin', 'action' => 'read', 'code' => 'fin.tax.efaktur.read', 'description' => 'View e-Faktur'],
            ['module' => 'fin', 'action' => 'create', 'code' => 'fin.tax.efaktur.create', 'description' => 'Request e-Faktur'],
            ['module' => 'fin', 'action' => 'approve', 'code' => 'fin.tax.efaktur.approve', 'description' => 'Approve e-Faktur'],
            ['module' => 'fin', 'action' => 'read', 'code' => 'fin.tax.spt.read', 'description' => 'View SPT Masa PPN'],
            ['module' => 'fin', 'action' => 'create', 'code' => 'fin.tax.spt.create', 'description' => 'Generate SPT Masa PPN'],
            ['module' => 'fin', 'action' => 'finalize', 'code' => 'fin.tax.spt.finalize', 'description' => 'Finalize SPT Masa PPN'],
            ['module' => 'fin', 'action' => 'read', 'code' => 'fin.tax.pph23.read', 'description' => 'View PPh 23 transactions'],
            ['module' => 'fin', 'action' => 'read', 'code' => 'fin.tax.ebupot.read', 'description' => 'View e-Bupot'],
            ['module' => 'fin', 'action' => 'create', 'code' => 'fin.tax.ebupot.create', 'description' => 'Issue e-Bupot'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['code' => $permission['code']],
                $permission,
            );
        }
    }
}