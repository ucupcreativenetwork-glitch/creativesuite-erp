<?php

namespace App\Modules\Iam\Config;

/**
 * Maps operational IAM role codes to business-module permissions.
 */
class RolePermissionCatalog
{
    private const HR_SELF = [
        'hr.attendance.read',
        'hr.attendance.clock',
        'hr.leave.read',
        'hr.leave.create',
    ];

    /** @return array<string, list<string>> */
    public static function permissionsByRole(): array
    {
        return [
            'FINANCE_STAFF' => array_merge(self::HR_SELF, self::financeStaff()),
            'FINANCE_SUPERVISOR' => array_merge(self::HR_SELF, self::financeSupervisor()),
            'ACCOUNTING_STAFF' => array_merge(self::HR_SELF, self::financeStaff()),
            'ACCOUNTING_SUPERVISOR' => array_merge(self::HR_SELF, self::financeSupervisor()),

            'HR_STAFF' => array_merge(self::HR_SELF, self::hrStaff()),
            'RECRUITER' => self::HR_SELF,
            'PAYROLL_STAFF' => array_merge(self::HR_SELF, self::hrStaff()),

            'SALES_STAFF' => array_merge(self::HR_SELF, self::salesStaff()),
            'SALES_SUPERVISOR' => array_merge(self::HR_SELF, self::salesSupervisor()),

            'MARKETING_STAFF' => array_merge(self::HR_SELF, self::salesStaff()),
            'MARKETING_SUPERVISOR' => array_merge(self::HR_SELF, self::salesSupervisor()),

            'PROJECT_STAFF' => array_merge(self::HR_SELF, self::projectStaff()),
            'PROJECT_COORDINATOR' => array_merge(self::HR_SELF, self::projectSupervisor()),
            'PROJECT_SUPERVISOR' => array_merge(self::HR_SELF, self::projectSupervisor()),

            'PURCHASING_STAFF' => array_merge(self::HR_SELF, self::purchasingStaff()),
            'PURCHASING_SUPERVISOR' => array_merge(self::HR_SELF, self::purchasingSupervisor()),

            'WAREHOUSE_STAFF' => array_merge(self::HR_SELF, self::warehouseStaff()),
            'WAREHOUSE_SUPERVISOR' => array_merge(self::HR_SELF, self::warehouseSupervisor()),

            'TECHNICIAN' => array_merge(self::HR_SELF, self::opsStaff()),
            'TECHNICAL_SUPERVISOR' => array_merge(self::HR_SELF, self::opsSupervisor()),
        ];
    }

    /** @return list<string> */
    private static function financeStaff(): array
    {
        return [
            'rpt.dashboard.read',
            'fin.coa.read',
            'fin.journal.read', 'fin.journal.create', 'fin.journal.post',
            'fin.invoice.read', 'fin.invoice.create', 'fin.invoice.update', 'fin.invoice.post',
            'fin.payment.read', 'fin.payment.create', 'fin.payment.post',
            'fin.report.read',
            'fin.bank_recon.read', 'fin.bank_recon.create', 'fin.bank_recon.match',
            'fin.tax.ppn.read', 'fin.tax.efaktur.read', 'fin.tax.efaktur.create',
            'fin.tax.spt.read', 'fin.tax.spt.create',
            'fin.tax.pph23.read', 'fin.tax.ebupot.read', 'fin.tax.ebupot.create',
            'fin.fiscal_period.read',
        ];
    }

    /** @return list<string> */
    private static function financeSupervisor(): array
    {
        return array_merge(self::financeStaff(), [
            'fin.coa.create', 'fin.coa.update',
            'fin.tax.efaktur.approve', 'fin.tax.spt.finalize',
            'fin.fiscal_period.close', 'fin.fiscal_period.lock',
        ]);
    }

    /** @return list<string> */
    private static function hrStaff(): array
    {
        return array_merge(self::HR_SELF, [
            'rpt.dashboard.read',
            'hr.employee.read', 'hr.employee.create', 'hr.employee.update',
            'hr.payroll.read', 'hr.payroll.create', 'hr.payroll.calculate', 'hr.payroll.post',
            'hr.leave.approve', 'hr.leave.manage',
            'hr.attendance.manage', 'hr.attendance.report',
        ]);
    }

    /** @return list<string> */
    private static function salesStaff(): array
    {
        return [
            'rpt.dashboard.read',
            'crm.account.read', 'crm.account.create', 'crm.account.update',
            'crm.contact.read', 'crm.contact.create',
            'sales.quotation.read', 'sales.quotation.create', 'sales.quotation.update',
            'sales.quotation.send', 'sales.quotation.accept',
            'prj.project.read',
        ];
    }

    /** @return list<string> */
    private static function salesSupervisor(): array
    {
        return array_merge(self::salesStaff(), [
            'sales.quotation.delete',
            'prj.project.read', 'prj.project.create',
        ]);
    }

    /** @return list<string> */
    private static function projectStaff(): array
    {
        return [
            'rpt.dashboard.read',
            'crm.account.read',
            'prj.project.read',
            'prj.timesheet.read', 'prj.timesheet.create', 'prj.timesheet.update',
            'prj.milestone.read',
        ];
    }

    /** @return list<string> */
    private static function projectSupervisor(): array
    {
        return array_merge(self::projectStaff(), [
            'prj.project.create', 'prj.project.update',
            'prj.timesheet.delete',
            'prj.milestone.create', 'prj.milestone.update', 'prj.milestone.delete', 'prj.milestone.invoice',
            'sales.quotation.read',
        ]);
    }

    /** @return list<string> */
    private static function purchasingStaff(): array
    {
        return [
            'rpt.dashboard.read',
            'crm.account.read',
            'pur.order.read', 'pur.order.create', 'pur.order.update', 'pur.order.submit',
            'inv.item.read', 'inv.warehouse.read', 'inv.balance.read', 'inv.movement.read',
        ];
    }

    /** @return list<string> */
    private static function purchasingSupervisor(): array
    {
        return array_merge(self::purchasingStaff(), [
            'pur.order.approve', 'pur.order.receive', 'pur.order.delete',
            'inv.item.create', 'inv.movement.create',
        ]);
    }

    /** @return list<string> */
    private static function warehouseStaff(): array
    {
        return [
            'rpt.dashboard.read',
            'inv.item.read', 'inv.warehouse.read', 'inv.balance.read',
            'inv.movement.read', 'inv.movement.create',
        ];
    }

    /** @return list<string> */
    private static function warehouseSupervisor(): array
    {
        return array_merge(self::warehouseStaff(), [
            'inv.item.create', 'inv.item.update',
            'inv.warehouse.create', 'inv.warehouse.update',
            'pur.order.read', 'pur.order.receive',
        ]);
    }

    /** @return list<string> */
    private static function opsStaff(): array
    {
        return [
            'rpt.dashboard.read',
            'crm.account.read',
            'ops.ticket.read', 'ops.ticket.create', 'ops.ticket.update',
            'ops.work_order.read', 'ops.work_order.create', 'ops.work_order.update',
        ];
    }

    /** @return list<string> */
    private static function opsSupervisor(): array
    {
        return array_merge(self::opsStaff(), [
            'ops.ticket.assign', 'ops.ticket.resolve', 'ops.ticket.close', 'ops.ticket.delete',
            'ops.work_order.assign', 'ops.work_order.complete', 'ops.work_order.delete',
        ]);
    }
}