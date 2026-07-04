<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade schema akuntansi untuk environment yang sudah menjalankan
 * migrasi finance lama (dengan tabel invoice/payment/tax).
 *
 * - Menambah kolom & tabel akuntansi yang belum ada
 * - Menghapus tabel non-akuntansi (invoice, payment, tax compliance)
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropNonAccountingTables();

        if (Schema::hasTable('cs_fin_journal_entries')) {
            Schema::table('cs_fin_journal_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('cs_fin_journal_entries', 'reversal_of_id')) {
                    $table->foreignId('reversal_of_id')->nullable()
                        ->after('source_id')
                        ->constrained('cs_fin_journal_entries')->nullOnDelete();
                }
                if (! Schema::hasColumn('cs_fin_journal_entries', 'voided_at')) {
                    $table->timestamp('voided_at')->nullable()->after('posted_by');
                    $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
                    $table->string('void_reason', 500)->nullable()->after('voided_by');
                }
            });
        }

        if (Schema::hasTable('cs_fin_journal_entry_lines')
            && ! Schema::hasColumn('cs_fin_journal_entry_lines', 'cost_center_code')) {
            Schema::table('cs_fin_journal_entry_lines', function (Blueprint $table) {
                $table->string('cost_center_code', 30)->nullable()->after('credit');
            });
        }

        if (! Schema::hasTable('cs_fin_period_account_balances')) {
            Schema::create('cs_fin_period_account_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
                $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
                $table->foreignId('fiscal_period_id')->constrained('cs_fin_fiscal_periods')->restrictOnDelete();
                $table->foreignId('account_id')->constrained('cs_fin_chart_of_accounts')->restrictOnDelete();
                $table->decimal('opening_debit', 18, 2)->default(0);
                $table->decimal('opening_credit', 18, 2)->default(0);
                $table->decimal('period_debit', 18, 2)->default(0);
                $table->decimal('period_credit', 18, 2)->default(0);
                $table->decimal('closing_debit', 18, 2)->default(0);
                $table->decimal('closing_credit', 18, 2)->default(0);
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();

                $table->unique(['fiscal_period_id', 'account_id'], 'fin_period_account_balances_unique');
                $table->index(['tenant_id', 'company_id', 'fiscal_period_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_fin_period_account_balances');

        if (Schema::hasTable('cs_fin_journal_entry_lines')
            && Schema::hasColumn('cs_fin_journal_entry_lines', 'cost_center_code')) {
            Schema::table('cs_fin_journal_entry_lines', function (Blueprint $table) {
                $table->dropColumn('cost_center_code');
            });
        }

        if (Schema::hasTable('cs_fin_journal_entries')) {
            Schema::table('cs_fin_journal_entries', function (Blueprint $table) {
                if (Schema::hasColumn('cs_fin_journal_entries', 'reversal_of_id')) {
                    $table->dropForeign(['reversal_of_id']);
                    $table->dropColumn('reversal_of_id');
                }
                if (Schema::hasColumn('cs_fin_journal_entries', 'voided_at')) {
                    $table->dropColumn(['voided_at', 'voided_by', 'void_reason']);
                }
            });
        }
    }

    protected function dropNonAccountingTables(): void
    {
        if (Schema::hasTable('cs_tax_pph23_transactions')) {
            Schema::table('cs_tax_pph23_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('cs_tax_pph23_transactions', 'ebupot_document_id')) {
                    $table->dropForeign(['ebupot_document_id']);
                }
            });
        }

        if (Schema::hasTable('cs_tax_ppn_transactions')) {
            Schema::table('cs_tax_ppn_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('cs_tax_ppn_transactions', 'efaktur_document_id')) {
                    $table->dropForeign(['efaktur_document_id']);
                }
            });
        }

        Schema::dropIfExists('cs_tax_ebupot_documents');
        Schema::dropIfExists('cs_tax_pph23_transactions');
        Schema::dropIfExists('cs_tax_spt_masa_ppn');
        Schema::dropIfExists('cs_tax_efaktur_documents');
        Schema::dropIfExists('cs_tax_ppn_transactions');
        Schema::dropIfExists('cs_fin_payments');
        Schema::dropIfExists('cs_fin_invoice_lines');
        Schema::dropIfExists('cs_fin_invoices');
    }
};