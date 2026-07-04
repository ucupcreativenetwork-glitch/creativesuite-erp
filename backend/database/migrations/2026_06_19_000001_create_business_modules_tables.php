<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational modules: CRM, Sales, Ops, Inventory, Purchasing, HR/Payroll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_crm_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('account_code', 30);
            $table->string('name', 300);
            $table->enum('account_type', ['CUSTOMER', 'VENDOR', 'BOTH'])->default('CUSTOMER');
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'PROSPECT'])->default('ACTIVE');
            $table->string('email', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('npwp', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'company_id', 'account_code']);
            $table->index(['tenant_id', 'company_id', 'status']);
        });

        Schema::create('cs_crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('cs_crm_accounts')->cascadeOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('full_name', 200);
            $table->string('job_title', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cs_sales_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('quotation_number', 30);
            $table->foreignId('account_id')->nullable()->constrained('cs_crm_accounts')->nullOnDelete();
            $table->string('customer_name', 300);
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->enum('status', ['DRAFT', 'SENT', 'ACCEPTED', 'REJECTED', 'EXPIRED'])->default('DRAFT');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'company_id', 'quotation_number']);
        });

        Schema::create('cs_sales_quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('cs_sales_quotations')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('description', 500);
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->unique(['quotation_id', 'line_number']);
        });

        Schema::create('cs_ops_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('ticket_number', 30);
            $table->foreignId('account_id')->nullable()->constrained('cs_crm_accounts')->nullOnDelete();
            $table->string('subject', 300);
            $table->text('description')->nullable();
            $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])->default('MEDIUM');
            $table->enum('status', ['OPEN', 'IN_PROGRESS', 'WAITING', 'RESOLVED', 'CLOSED'])->default('OPEN');
            $table->foreignId('assigned_to')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'company_id', 'ticket_number']);
        });

        Schema::create('cs_ops_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('work_order_number', 30);
            $table->foreignId('ticket_id')->nullable()->constrained('cs_ops_tickets')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('cs_crm_accounts')->nullOnDelete();
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->enum('status', ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('SCHEDULED');
            $table->date('scheduled_date')->nullable();
            $table->foreignId('technician_id')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'company_id', 'work_order_number']);
        });

        Schema::create('cs_inv_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('sku', 50);
            $table->string('name', 300);
            $table->string('uom', 20)->default('PCS');
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->decimal('reorder_level', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'company_id', 'sku']);
        });

        Schema::create('cs_inv_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('cs_core_branches')->nullOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'company_id', 'code']);
        });

        Schema::create('cs_inv_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('cs_inv_items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('cs_inv_warehouses')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['item_id', 'warehouse_id']);
        });

        Schema::create('cs_inv_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('movement_number', 30);
            $table->foreignId('item_id')->constrained('cs_inv_items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('cs_inv_warehouses')->cascadeOnDelete();
            $table->enum('movement_type', ['IN', 'OUT', 'ADJUST'])->default('IN');
            $table->decimal('quantity', 14, 4);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'company_id', 'movement_number']);
        });

        Schema::create('cs_pur_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('po_number', 30);
            $table->foreignId('vendor_id')->nullable()->constrained('cs_crm_accounts')->nullOnDelete();
            $table->string('vendor_name', 300);
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'RECEIVED', 'CANCELLED'])->default('DRAFT');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'company_id', 'po_number']);
        });

        Schema::create('cs_pur_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('cs_pur_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('item_id')->nullable()->constrained('cs_inv_items')->nullOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->unique(['purchase_order_id', 'line_number']);
        });

        Schema::create('cs_hr_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('employee_number', 30);
            $table->string('full_name', 200);
            $table->string('email', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->decimal('base_salary', 18, 2)->default(0);
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'TERMINATED'])->default('ACTIVE');
            $table->date('hire_date')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'company_id', 'employee_number']);
        });

        Schema::create('cs_hr_payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('run_number', 30);
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->enum('status', ['DRAFT', 'CALCULATED', 'POSTED'])->default('DRAFT');
            $table->decimal('total_gross', 18, 2)->default(0);
            $table->decimal('total_deductions', 18, 2)->default(0);
            $table->decimal('total_net', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'company_id', 'run_number']);
            $table->unique(['tenant_id', 'company_id', 'period_year', 'period_month']);
        });

        Schema::create('cs_hr_payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('cs_hr_payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('cs_hr_employees')->cascadeOnDelete();
            $table->decimal('gross_salary', 18, 2);
            $table->decimal('pph21_amount', 18, 2)->default(0);
            $table->decimal('bpjs_amount', 18, 2)->default(0);
            $table->decimal('other_deductions', 18, 2)->default(0);
            $table->decimal('net_salary', 18, 2);
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_hr_payroll_lines');
        Schema::dropIfExists('cs_hr_payroll_runs');
        Schema::dropIfExists('cs_hr_employees');
        Schema::dropIfExists('cs_pur_order_lines');
        Schema::dropIfExists('cs_pur_orders');
        Schema::dropIfExists('cs_inv_stock_movements');
        Schema::dropIfExists('cs_inv_stock_balances');
        Schema::dropIfExists('cs_inv_warehouses');
        Schema::dropIfExists('cs_inv_items');
        Schema::dropIfExists('cs_ops_work_orders');
        Schema::dropIfExists('cs_ops_tickets');
        Schema::dropIfExists('cs_sales_quotation_lines');
        Schema::dropIfExists('cs_sales_quotations');
        Schema::dropIfExists('cs_crm_contacts');
        Schema::dropIfExists('cs_crm_accounts');
    }
};