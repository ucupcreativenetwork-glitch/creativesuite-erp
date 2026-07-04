<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CreativeSuite ERP — Accounting Database (Double-Entry)
 *
 * Prinsip:
 * - Setiap transaksi dicatat di cs_fin_journal_entries (header) + cs_fin_journal_entry_lines (detail).
 * - Total debit HARUS sama dengan total credit per journal entry.
 * - Hanya status POSTED yang mempengaruhi Buku Besar & Neraca Saldo.
 * - Buku Besar = agregasi journal_entry_lines dari entry berstatus POSTED.
 * - Posting hanya ke akun DETAIL yang is_postable = true.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Chart of Accounts (COA) ─────────────────────────────────
        Schema::create('cs_fin_chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('code', 20);                          // format 5-digit: X-XX-XXX
            $table->string('name', 200);
            $table->unsignedTinyInteger('category');             // 1=Aset, 2=Liab, 3=Ekuitas, 4=Pendapatan, 5=HPP, 6=Beban, 7=Lainnya
            $table->enum('account_type', ['HEADER', 'DETAIL'])->default('DETAIL');
            $table->foreignId('parent_id')->nullable()->constrained('cs_fin_chart_of_accounts')->nullOnDelete();
            $table->enum('normal_balance', ['DEBIT', 'CREDIT']);
            $table->boolean('is_postable')->default(true);       // false untuk HEADER / grouping
            $table->boolean('is_active')->default(true);
            $table->string('description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'company_id', 'code']);
            $table->index(['tenant_id', 'company_id', 'category', 'is_active']);
            $table->index(['parent_id']);
        });

        // ── 2. Fiscal Periods (kontrol periode & lock posting) ─────────
        Schema::create('cs_fin_fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('name', 20);                          // e.g. 2026-06
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['OPEN', 'CLOSED', 'LOCKED'])->default('OPEN');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'company_id', 'year', 'month']);
            $table->index(['tenant_id', 'company_id', 'status']);
        });

        // ── 3. Journal Entries (header transaksi double-entry) ─────────
        Schema::create('cs_fin_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('cs_core_branches')->nullOnDelete();
            $table->char('public_id', 36)->unique();
            $table->string('entry_number', 30);
            $table->date('entry_date');
            $table->enum('journal_type', [
                'SALES', 'PURCHASE', 'CASH_IN', 'CASH_OUT', 'INVENTORY', 'COGS',
                'PAYROLL', 'TAX', 'MANUAL', 'REVERSAL', 'CLOSING',
            ])->default('MANUAL');
            $table->enum('status', ['DRAFT', 'POSTED', 'VOID'])->default('DRAFT');
            $table->foreignId('fiscal_period_id')->constrained('cs_fin_fiscal_periods')->restrictOnDelete();
            $table->string('description', 500)->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->string('source_type', 100)->nullable();        // polymorphic: modul sumber (Invoice, Payment, dll)
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('reversal_of_id')->nullable()
                ->constrained('cs_fin_journal_entries')->nullOnDelete();
            $table->decimal('total_debit', 18, 2)->default(0);    // harus = total_credit saat POSTED
            $table->decimal('total_credit', 18, 2)->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'company_id', 'entry_number']);
            $table->index(['tenant_id', 'company_id', 'entry_date', 'status']);
            $table->index(['fiscal_period_id', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index(['reversal_of_id']);
        });

        // ── 4. Journal Entry Lines (detail debit/kredit) ───────────────
        Schema::create('cs_fin_journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->constrained('cs_fin_journal_entries')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('account_id')->constrained('cs_fin_chart_of_accounts')->restrictOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('debit', 18, 2)->default(0);         // salah satu: debit ATAU credit > 0
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('cost_center_code', 30)->nullable();    // opsional: segmentasi beban
            $table->timestamps();

            $table->unique(['journal_entry_id', 'line_number']);
            $table->index(['tenant_id', 'account_id']);
            $table->index(['journal_entry_id']);
        });

        // ── 5. Account Mappings (default akun untuk auto-journal) ──────
        Schema::create('cs_fin_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
            $table->string('mapping_key', 50);                   // AR_ACCOUNT, REVENUE_ACCOUNT, PPN_OUTPUT, dll
            $table->foreignId('account_id')->constrained('cs_fin_chart_of_accounts')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'company_id', 'mapping_key']);
        });

        // ── 6. Period Account Balances (saldo per periode — closing/trial balance) ─
        Schema::create('cs_fin_period_account_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
            $table->foreignId('fiscal_period_id')->constrained('cs_fin_fiscal_periods')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('cs_fin_chart_of_accounts')->restrictOnDelete();
            $table->decimal('opening_debit', 18, 2)->default(0);
            $table->decimal('opening_credit', 18, 2)->default(0);
            $table->decimal('period_debit', 18, 2)->default(0);  // mutasi debit periode berjalan
            $table->decimal('period_credit', 18, 2)->default(0); // mutasi credit periode berjalan
            $table->decimal('closing_debit', 18, 2)->default(0);
            $table->decimal('closing_credit', 18, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['fiscal_period_id', 'account_id'], 'fin_period_account_balances_unique');
            $table->index(['tenant_id', 'company_id', 'fiscal_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_fin_period_account_balances');
        Schema::dropIfExists('cs_fin_account_mappings');
        Schema::dropIfExists('cs_fin_journal_entry_lines');
        Schema::dropIfExists('cs_fin_journal_entries');
        Schema::dropIfExists('cs_fin_fiscal_periods');
        Schema::dropIfExists('cs_fin_chart_of_accounts');
    }
};