<?php

namespace Database\Seeders;

use App\Modules\Business\Enums\AccountStatus;
use App\Modules\Business\Enums\AccountType;
use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Enums\MilestoneStatus;
use App\Modules\Business\Enums\PayrollRunStatus;
use App\Modules\Business\Enums\ProjectStatus;
use App\Modules\Business\Enums\PurchaseOrderStatus;
use App\Modules\Business\Enums\QuotationStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\CrmAccount;
use App\Modules\Business\Models\CrmContact;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Models\InvItem;
use App\Modules\Business\Models\InvStockBalance;
use App\Modules\Business\Models\InvWarehouse;
use App\Modules\Business\Models\Milestone;
use App\Modules\Business\Models\PayrollRun;
use App\Modules\Business\Models\Project;
use App\Modules\Business\Models\PurchaseOrder;
use App\Modules\Business\Models\PurchaseOrderLine;
use App\Modules\Business\Models\Quotation;
use App\Modules\Business\Models\QuotationLine;
use App\Modules\Core\Enums\EntityType;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyAccess;
use App\Modules\Finance\Services\CoaSetupService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoAgencySeeder extends Seeder
{
    public function run(): void
    {
        if (Tenant::query()->where('slug', 'pt-demo')->exists()) {
            return;
        }

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Demo Agency',
            'slug' => 'pt-demo',
            'status' => TenantStatus::Trial,
            'max_users' => 25,
            'max_branches' => 3,
            'max_storage_mb' => 5120,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
            'trial_ends_at' => now()->addDays(30),
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Demo Agency',
            'trade_name' => 'Demo Agency',
            'entity_type' => EntityType::Pt,
            'email' => 'admin@demo.id',
            'phone' => '0215550100',
            'is_pkp' => true,
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'Head Office',
            'is_head_office' => true,
            'is_active' => true,
            'attendance_geofence_enabled' => true,
            'attendance_latitude' => -6.208800,
            'attendance_longitude' => 106.845600,
            'attendance_geofence_radius_m' => 150,
        ]);

        app(CoaSetupService::class)->setupForCompany($tenant->id, $company->id);

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'TENANT_OWNER',
            'name' => 'Tenant Owner',
            'description' => 'Full access to demo tenant',
            'is_system' => true,
            'is_active' => true,
        ]);
        $role->permissions()->sync(Permission::query()->pluck('id'));

        $owner = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'admin@demo.id',
            'password' => 'Password123',
            'full_name' => 'Demo Owner',
            'phone' => '081234567890',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $owner->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        $client = CrmAccount::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'account_code' => 'C-0001',
            'name' => 'PT Klien Digital',
            'account_type' => AccountType::Customer,
            'status' => AccountStatus::Active,
            'email' => 'finance@kliendigital.id',
            'phone' => '0215550200',
            'npwp' => '01.234.567.8-901.000',
            'city' => 'Jakarta',
            'created_by' => $owner->id,
        ]);

        CrmContact::query()->create([
            'tenant_id' => $tenant->id,
            'account_id' => $client->id,
            'public_id' => (string) Str::uuid(),
            'full_name' => 'Budi Santoso',
            'job_title' => 'Marketing Manager',
            'email' => 'budi@kliendigital.id',
            'phone' => '08111222333',
            'is_primary' => true,
        ]);

        $vendor = CrmAccount::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'account_code' => 'V-0001',
            'name' => 'PT Supplier Teknologi',
            'account_type' => AccountType::Vendor,
            'status' => AccountStatus::Active,
            'email' => 'sales@suppliertek.id',
            'phone' => '0215550300',
            'npwp' => '02.345.678.9-012.000',
            'city' => 'Tangerang',
            'created_by' => $owner->id,
        ]);

        CrmContact::query()->create([
            'tenant_id' => $tenant->id,
            'account_id' => $vendor->id,
            'public_id' => (string) Str::uuid(),
            'full_name' => 'Siti Rahayu',
            'job_title' => 'Account Executive',
            'email' => 'siti@suppliertek.id',
            'phone' => '08122333444',
            'is_primary' => true,
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'quotation_number' => 'QT-'.now()->format('Ym').'-0001',
            'account_id' => $client->id,
            'customer_name' => $client->name,
            'quotation_date' => now()->subDays(7)->toDateString(),
            'valid_until' => now()->addDays(23)->toDateString(),
            'status' => QuotationStatus::Sent,
            'subtotal' => 45000000,
            'discount_amount' => 0,
            'tax_amount' => 5400000,
            'total_amount' => 50400000,
            'notes' => 'Paket branding & digital campaign Q3.',
            'created_by' => $owner->id,
        ]);

        QuotationLine::query()->create([
            'quotation_id' => $quotation->id,
            'line_number' => 1,
            'description' => 'Creative concept & key visual',
            'quantity' => 1,
            'unit_price' => 20000000,
            'amount' => 20000000,
        ]);
        QuotationLine::query()->create([
            'quotation_id' => $quotation->id,
            'line_number' => 2,
            'description' => 'Social media management (3 bulan)',
            'quantity' => 3,
            'unit_price' => 5000000,
            'amount' => 15000000,
        ]);
        QuotationLine::query()->create([
            'quotation_id' => $quotation->id,
            'line_number' => 3,
            'description' => 'Landing page development',
            'quantity' => 1,
            'unit_price' => 10000000,
            'amount' => 10000000,
        ]);

        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'project_number' => 'PRJ-'.now()->format('Ym').'-0001',
            'name' => 'Campaign Digital Klien Digital',
            'account_id' => $client->id,
            'quotation_id' => $quotation->id,
            'status' => ProjectStatus::Active,
            'budget' => 50400000,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'notes' => 'Proyek demo untuk alur quotation accepted.',
            'created_by' => $owner->id,
        ]);

        Milestone::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'name' => 'Kick-off & creative delivery',
            'description' => 'Presentasi konsep dan materi awal.',
            'amount' => 20000000,
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => MilestoneStatus::Pending,
            'sort_order' => 1,
            'created_by' => $owner->id,
        ]);
        Milestone::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'name' => 'Campaign go-live',
            'description' => 'Peluncuran kampanye digital.',
            'amount' => 30400000,
            'due_date' => now()->addMonths(2)->toDateString(),
            'status' => MilestoneStatus::Pending,
            'sort_order' => 2,
            'created_by' => $owner->id,
        ]);

        $employees = [
            [
                'employee_number' => 'EMP-0001',
                'full_name' => 'Andi Pratama',
                'email' => 'andi@demo.id',
                'job_title' => 'Account Manager',
                'department' => 'Client Service',
                'base_salary' => 12000000,
            ],
            [
                'employee_number' => 'EMP-0002',
                'full_name' => 'Dewi Lestari',
                'email' => 'dewi@demo.id',
                'job_title' => 'Creative Lead',
                'department' => 'Creative',
                'base_salary' => 15000000,
            ],
        ];

        $employeeModels = [];
        foreach ($employees as $data) {
            $employeeModels[] = Employee::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'public_id' => (string) Str::uuid(),
                'employee_number' => $data['employee_number'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'job_title' => $data['job_title'],
                'department' => $data['department'],
                'base_salary' => $data['base_salary'],
                'ter_category' => 'A',
                'status' => EmployeeStatus::Active,
                'hire_date' => now()->subMonths(6)->toDateString(),
            ]);
        }

        foreach ($employeeModels as $employee) {
            AttendanceRecord::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'public_id' => (string) Str::uuid(),
                'employee_id' => $employee->id,
                'attendance_date' => now()->startOfMonth()->addDays(2)->toDateString(),
                'clock_in_at' => now()->startOfMonth()->addDays(2)->setTime(8, 55),
                'clock_out_at' => now()->startOfMonth()->addDays(2)->setTime(17, 5),
                'status' => AttendanceStatus::Present,
                'work_minutes' => 480,
                'created_by' => $owner->id,
            ]);
            AttendanceRecord::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'public_id' => (string) Str::uuid(),
                'employee_id' => $employee->id,
                'attendance_date' => now()->startOfMonth()->addDays(3)->toDateString(),
                'clock_in_at' => now()->startOfMonth()->addDays(3)->setTime(9, 10),
                'clock_out_at' => now()->startOfMonth()->addDays(3)->setTime(17, 0),
                'status' => AttendanceStatus::Late,
                'work_minutes' => 470,
                'late_minutes' => 10,
                'created_by' => $owner->id,
            ]);
        }

        $warehouse = InvWarehouse::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'public_id' => (string) Str::uuid(),
            'code' => 'WH-01',
            'name' => 'Gudang Utama',
            'is_active' => true,
        ]);

        $items = [
            ['sku' => 'CAM-HD01', 'name' => 'Kamera CCTV 2MP', 'unit_cost' => 850000],
            ['sku' => 'CAB-UTP', 'name' => 'Kabel UTP Cat6 (100m)', 'unit_cost' => 450000],
            ['sku' => 'NVR-8CH', 'name' => 'NVR 8 Channel', 'unit_cost' => 1750000],
        ];

        $itemModels = [];
        foreach ($items as $item) {
            $itemModels[] = InvItem::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'public_id' => (string) Str::uuid(),
                'sku' => $item['sku'],
                'name' => $item['name'],
                'uom' => 'PCS',
                'unit_cost' => $item['unit_cost'],
                'reorder_level' => 5,
                'is_active' => true,
            ]);
        }

        foreach ($itemModels as $index => $item) {
            InvStockBalance::query()->create([
                'item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'quantity_on_hand' => $index + 2,
            ]);
        }

        $po = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'po_number' => 'PO-'.now()->format('Ym').'-0001',
            'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->name,
            'order_date' => now()->subDays(2)->toDateString(),
            'expected_date' => now()->addDays(5)->toDateString(),
            'status' => PurchaseOrderStatus::Approved,
            'subtotal' => 5150000,
            'total_amount' => 5150000,
            'notes' => 'Restock perangkat instalasi demo.',
            'created_by' => $owner->id,
        ]);

        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $itemModels[0]->id,
            'description' => $itemModels[0]->name,
            'quantity' => 4,
            'unit_price' => 850000,
            'amount' => 3400000,
        ]);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_number' => 2,
            'item_id' => $itemModels[1]->id,
            'description' => $itemModels[1]->name,
            'quantity' => 2,
            'unit_price' => 450000,
            'amount' => 900000,
        ]);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_number' => 3,
            'item_id' => $itemModels[2]->id,
            'description' => $itemModels[2]->name,
            'quantity' => 1,
            'unit_price' => 850000,
            'amount' => 850000,
        ]);

        PayrollRun::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'run_number' => 'PAY-'.now()->format('Ym').'-0001',
            'period_year' => (int) now()->year,
            'period_month' => (int) now()->month,
            'status' => PayrollRunStatus::Draft,
            'created_by' => $owner->id,
        ]);
    }
}