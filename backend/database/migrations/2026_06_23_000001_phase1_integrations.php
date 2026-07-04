<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_sales_quotations', function (Blueprint $table): void {
            $table->unsignedBigInteger('invoice_id')->nullable()->after('account_id');
            $table->unsignedBigInteger('project_id')->nullable()->after('invoice_id');
        });

        Schema::table('cs_fin_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('quotation_id')->nullable()->after('branch_id');
        });

        Schema::table('cs_hr_payroll_runs', function (Blueprint $table): void {
            $table->unsignedBigInteger('journal_entry_id')->nullable()->after('total_net');
        });

        Schema::create('cs_prj_projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('company_id');
            $table->uuid('public_id')->unique();
            $table->string('project_number', 30);
            $table->string('name', 200);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->decimal('budget', 18, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'company_id', 'project_number']);
            $table->index(['tenant_id', 'company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_prj_projects');

        Schema::table('cs_hr_payroll_runs', function (Blueprint $table): void {
            $table->dropColumn('journal_entry_id');
        });

        Schema::table('cs_fin_invoices', function (Blueprint $table): void {
            $table->dropColumn('quotation_id');
        });

        Schema::table('cs_sales_quotations', function (Blueprint $table): void {
            $table->dropColumn(['invoice_id', 'project_id']);
        });
    }
};