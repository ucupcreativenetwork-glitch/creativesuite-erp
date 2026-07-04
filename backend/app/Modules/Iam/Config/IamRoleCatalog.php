<?php

namespace App\Modules\Iam\Config;

/**
 * Enterprise IAM catalog — single source of truth for departments, roles, and positions.
 */
class IamRoleCatalog
{
    /** @var array<string, string> */
    public const DEPARTMENTS = [
        'FIN' => 'Finance',
        'ACC' => 'Accounting',
        'HRD' => 'Human Resources',
        'SAL' => 'Sales',
        'MKT' => 'Marketing',
        'PUR' => 'Purchasing',
        'WH' => 'Warehouse',
        'TS' => 'Technical Support',
        'PRJ' => 'Project',
    ];

    /** Maps department code → HEAD_* role code */
    public const HEAD_ROLE_BY_DEPT = [
        'FIN' => 'HEAD_FINANCE',
        'ACC' => 'HEAD_ACCOUNTING',
        'HRD' => 'HEAD_HRD',
        'SAL' => 'HEAD_SALES',
        'MKT' => 'HEAD_MARKETING',
        'PUR' => 'HEAD_PURCHASING',
        'WH' => 'HEAD_WAREHOUSE',
        'TS' => 'HEAD_TECHNICAL',
        'PRJ' => 'HEAD_PROJECT',
    ];

    /**
     * @return array<string, array{code: string, name: string, dept?: string, perms?: list<string>}>
     */
    public static function roles(): array
    {
        return [
            // Executive (not requestable by Head Divisi)
            'DIRECTOR' => ['code' => 'DIRECTOR', 'name' => 'Director'],
            'GENERAL_MANAGER' => [
                'code' => 'GENERAL_MANAGER',
                'name' => 'General Manager',
                'perms' => [
                    'iam.request.read.all', 'iam.request.approve', 'iam.request.reject',
                    'iam.request.request_revision', 'iam.audit.read',
                ],
            ],

            // Head Division
            'HEAD_FINANCE' => [
                'code' => 'HEAD_FINANCE', 'name' => 'Head Finance', 'dept' => 'FIN',
                'perms' => ['iam.request.create', 'iam.request.read.own', 'iam.request.update.own', 'iam.request.cancel.own'],
            ],
            'HEAD_ACCOUNTING' => [
                'code' => 'HEAD_ACCOUNTING', 'name' => 'Head Accounting', 'dept' => 'ACC',
                'perms' => ['iam.request.create', 'iam.request.read.own', 'iam.request.update.own', 'iam.request.cancel.own'],
            ],
            'HEAD_HRD' => [
                'code' => 'HEAD_HRD', 'name' => 'Head HRD', 'dept' => 'HRD',
                'perms' => ['iam.request.create', 'iam.request.read.own', 'iam.request.update.own', 'iam.request.cancel.own'],
            ],
            'HEAD_SALES' => [
                'code' => 'HEAD_SALES', 'name' => 'Head Sales', 'dept' => 'SAL',
                'perms' => ['iam.request.create', 'iam.request.read.own', 'iam.request.update.own', 'iam.request.cancel.own'],
            ],
            'HEAD_MARKETING' => [
                'code' => 'HEAD_MARKETING', 'name' => 'Head Marketing', 'dept' => 'MKT',
                'perms' => ['iam.request.create', 'iam.request.read.own', 'iam.request.update.own', 'iam.request.cancel.own'],
            ],
            'HEAD_PURCHASING' => [
                'code' => 'HEAD_PURCHASING', 'name' => 'Head Purchasing', 'dept' => 'PUR',
                'perms' => ['iam.request.create', 'iam.request.read.own', 'iam.request.update.own', 'iam.request.cancel.own'],
            ],
            'HEAD_WAREHOUSE' => [
                'code' => 'HEAD_WAREHOUSE', 'name' => 'Head Warehouse', 'dept' => 'WH',
                'perms' => ['iam.request.create', 'iam.request.read.own', 'iam.request.update.own', 'iam.request.cancel.own'],
            ],
            'HEAD_TECHNICAL' => [
                'code' => 'HEAD_TECHNICAL', 'name' => 'Head Technical Support', 'dept' => 'TS',
                'perms' => ['iam.request.create', 'iam.request.read.own', 'iam.request.update.own', 'iam.request.cancel.own'],
            ],
            'HEAD_PROJECT' => [
                'code' => 'HEAD_PROJECT', 'name' => 'Head Project', 'dept' => 'PRJ',
                'perms' => ['iam.request.create', 'iam.request.read.own', 'iam.request.update.own', 'iam.request.cancel.own'],
            ],

            // Finance
            'FINANCE_STAFF' => ['code' => 'FINANCE_STAFF', 'name' => 'Finance Staff', 'dept' => 'FIN'],
            'FINANCE_SUPERVISOR' => ['code' => 'FINANCE_SUPERVISOR', 'name' => 'Finance Supervisor', 'dept' => 'FIN'],

            // Accounting
            'ACCOUNTING_STAFF' => ['code' => 'ACCOUNTING_STAFF', 'name' => 'Accounting Staff', 'dept' => 'ACC'],
            'ACCOUNTING_SUPERVISOR' => ['code' => 'ACCOUNTING_SUPERVISOR', 'name' => 'Accounting Supervisor', 'dept' => 'ACC'],

            // HRD
            'HR_STAFF' => ['code' => 'HR_STAFF', 'name' => 'HR Staff', 'dept' => 'HRD'],
            'RECRUITER' => ['code' => 'RECRUITER', 'name' => 'Recruiter', 'dept' => 'HRD'],
            'PAYROLL_STAFF' => ['code' => 'PAYROLL_STAFF', 'name' => 'Payroll Staff', 'dept' => 'HRD'],

            // Sales
            'SALES_STAFF' => ['code' => 'SALES_STAFF', 'name' => 'Sales Staff', 'dept' => 'SAL'],
            'SALES_SUPERVISOR' => ['code' => 'SALES_SUPERVISOR', 'name' => 'Sales Supervisor', 'dept' => 'SAL'],

            // Marketing
            'MARKETING_STAFF' => ['code' => 'MARKETING_STAFF', 'name' => 'Marketing Staff', 'dept' => 'MKT'],
            'MARKETING_SUPERVISOR' => ['code' => 'MARKETING_SUPERVISOR', 'name' => 'Marketing Supervisor', 'dept' => 'MKT'],

            // Purchasing
            'PURCHASING_STAFF' => ['code' => 'PURCHASING_STAFF', 'name' => 'Purchasing Staff', 'dept' => 'PUR'],
            'PURCHASING_SUPERVISOR' => ['code' => 'PURCHASING_SUPERVISOR', 'name' => 'Purchasing Supervisor', 'dept' => 'PUR'],

            // Warehouse
            'WAREHOUSE_STAFF' => ['code' => 'WAREHOUSE_STAFF', 'name' => 'Warehouse Staff', 'dept' => 'WH'],
            'WAREHOUSE_SUPERVISOR' => ['code' => 'WAREHOUSE_SUPERVISOR', 'name' => 'Warehouse Supervisor', 'dept' => 'WH'],

            // Technical Support
            'TECHNICIAN' => ['code' => 'TECHNICIAN', 'name' => 'Technician', 'dept' => 'TS'],
            'TECHNICAL_SUPERVISOR' => ['code' => 'TECHNICAL_SUPERVISOR', 'name' => 'Technical Supervisor', 'dept' => 'TS'],

            // Project
            'PROJECT_STAFF' => ['code' => 'PROJECT_STAFF', 'name' => 'Project Staff', 'dept' => 'PRJ'],
            'PROJECT_COORDINATOR' => ['code' => 'PROJECT_COORDINATOR', 'name' => 'Project Coordinator', 'dept' => 'PRJ'],
            'PROJECT_SUPERVISOR' => ['code' => 'PROJECT_SUPERVISOR', 'name' => 'Project Supervisor', 'dept' => 'PRJ'],
        ];
    }

    /** @return array<string, list<string>> */
    public static function positionsByDepartment(): array
    {
        return [
            'FIN' => ['Finance Staff', 'Finance Supervisor', 'AR Staff', 'AP Staff'],
            'ACC' => ['Accounting Staff', 'Accounting Supervisor', 'Senior Accountant', 'Junior Accountant'],
            'HRD' => ['HR Staff', 'HR Supervisor', 'Recruiter', 'Payroll Staff'],
            'SAL' => ['Sales Staff', 'Sales Supervisor', 'Account Executive', 'Sales Representative'],
            'MKT' => ['Marketing Staff', 'Marketing Supervisor', 'Digital Marketing', 'Content Specialist'],
            'PUR' => ['Purchasing Staff', 'Purchasing Supervisor', 'Procurement Officer'],
            'WH' => ['Warehouse Staff', 'Warehouse Supervisor', 'Inventory Clerk', 'Logistics Staff'],
            'TS' => ['Technician', 'Technical Supervisor', 'Support Engineer', 'Field Engineer'],
            'PRJ' => ['Project Staff', 'Project Coordinator', 'Project Supervisor', 'Project Manager'],
        ];
    }

    public static function headRoleCodeForDepartment(string $deptCode): string
    {
        return self::HEAD_ROLE_BY_DEPT[strtoupper($deptCode)]
            ?? throw new \InvalidArgumentException("Unknown department code: {$deptCode}");
    }

    /** @return list<string> */
    public static function staffRoleCodesForDepartment(string $deptCode): array
    {
        $codes = [];
        foreach (self::roles() as $def) {
            if (($def['dept'] ?? null) === strtoupper($deptCode) && ! str_starts_with($def['code'], 'HEAD_')) {
                $codes[] = $def['code'];
            }
        }

        return $codes;
    }
}