<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cs_fin_invoices')) {
            Schema::create('cs_fin_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
                $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('cs_core_branches')->nullOnDelete();
                $table->char('public_id', 36)->unique();
                $table->string('invoice_number', 30);
                $table->enum('invoice_type', ['SALES', 'PURCHASE']);
                $table->date('invoice_date');
                $table->date('due_date')->nullable();
                $table->string('counterparty_name', 300);
                $table->string('counterparty_npwp', 20)->nullable();
                $table->enum('status', ['DRAFT', 'POSTED', 'PAID', 'VOID'])->default('DRAFT');
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('dpp_amount', 18, 2)->default(0);
                $table->decimal('ppn_rate', 5, 2)->default(12);
                $table->decimal('ppn_amount', 18, 2)->default(0);
                $table->decimal('pph23_amount', 18, 2)->default(0);
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->decimal('paid_amount', 18, 2)->default(0);
                $table->boolean('is_ppn_inclusive')->default(false);
                $table->boolean('is_pph23_applicable')->default(false);
                $table->text('notes')->nullable();
                $table->foreignId('journal_entry_id')->nullable()->constrained('cs_fin_journal_entries')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'company_id', 'invoice_number']);
                $table->index(['tenant_id', 'company_id', 'invoice_type', 'status']);
            });
        }

        if (! Schema::hasTable('cs_fin_invoice_lines')) {
            Schema::create('cs_fin_invoice_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('cs_fin_invoices')->cascadeOnDelete();
                $table->unsignedSmallInteger('line_number');
                $table->string('description', 500);
                $table->decimal('quantity', 12, 4)->default(1);
                $table->decimal('unit_price', 18, 2)->default(0);
                $table->decimal('amount', 18, 2)->default(0);
                $table->foreignId('account_id')->nullable()->constrained('cs_fin_chart_of_accounts')->nullOnDelete();
                $table->timestamps();
                $table->unique(['invoice_id', 'line_number']);
            });
        }

        if (! Schema::hasTable('cs_fin_payments')) {
            Schema::create('cs_fin_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
                $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('cs_core_branches')->nullOnDelete();
                $table->char('public_id', 36)->unique();
                $table->string('payment_number', 30);
                $table->enum('payment_type', ['AR_RECEIPT', 'AP_DISBURSEMENT']);
                $table->date('payment_date');
                $table->foreignId('invoice_id')->nullable()->constrained('cs_fin_invoices')->nullOnDelete();
                $table->string('counterparty_name', 300)->nullable();
                $table->string('counterparty_npwp', 20)->nullable();
                $table->decimal('amount', 18, 2);
                $table->decimal('pph23_amount', 18, 2)->default(0);
                $table->decimal('net_amount', 18, 2);
                $table->foreignId('bank_account_id')->constrained('cs_fin_chart_of_accounts')->restrictOnDelete();
                $table->enum('status', ['DRAFT', 'POSTED', 'VOID'])->default('DRAFT');
                $table->foreignId('journal_entry_id')->nullable()->constrained('cs_fin_journal_entries')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'company_id', 'payment_number']);
                $table->index(['tenant_id', 'company_id', 'payment_type', 'status']);
            });
        }

        if (! Schema::hasTable('cs_tax_ppn_transactions')) {
            Schema::create('cs_tax_ppn_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
                $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
                $table->string('source_type', 50);
                $table->unsignedBigInteger('source_id');
                $table->enum('transaction_type', ['OUTPUT', 'INPUT']);
                $table->date('transaction_date');
                $table->unsignedSmallInteger('fiscal_year');
                $table->unsignedTinyInteger('fiscal_month');
                $table->decimal('dpp_amount', 18, 2);
                $table->decimal('ppn_rate', 5, 2);
                $table->decimal('ppn_amount', 18, 2);
                $table->string('counterparty_name', 300)->nullable();
                $table->string('counterparty_npwp', 20)->nullable();
                $table->unsignedBigInteger('efaktur_document_id')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'company_id', 'fiscal_year', 'fiscal_month', 'transaction_type']);
            });
        }

        if (! Schema::hasTable('cs_tax_efaktur_documents')) {
            Schema::create('cs_tax_efaktur_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
                $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
                $table->char('public_id', 36)->unique();
                $table->foreignId('ppn_transaction_id')->constrained('cs_tax_ppn_transactions')->restrictOnDelete();
                $table->string('nomor_faktur', 20)->nullable();
                $table->enum('status', ['DRAFT', 'REQUESTED', 'APPROVED', 'CANCELLED'])->default('DRAFT');
                $table->string('buyer_npwp', 20)->nullable();
                $table->string('buyer_name', 300);
                $table->decimal('dpp', 18, 2);
                $table->decimal('ppn', 18, 2);
                $table->decimal('total', 18, 2);
                $table->date('tanggal_faktur');
                $table->string('djp_reference', 100)->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('cs_tax_ppn_transactions') && Schema::hasTable('cs_tax_efaktur_documents')) {
            Schema::table('cs_tax_ppn_transactions', function (Blueprint $table) {
                if (! $this->hasForeign('cs_tax_ppn_transactions', 'efaktur_document_id')) {
                    $table->foreign('efaktur_document_id')
                        ->references('id')->on('cs_tax_efaktur_documents')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('cs_tax_spt_masa_ppn')) {
            Schema::create('cs_tax_spt_masa_ppn', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
                $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->enum('status', ['DRAFT', 'FINALIZED'])->default('DRAFT');
                $table->decimal('total_pk', 18, 2)->default(0);
                $table->decimal('total_pm', 18, 2)->default(0);
                $table->decimal('kurang_lebih_bayar', 18, 2)->default(0);
                $table->json('data_json')->nullable();
                $table->timestamp('finalized_at')->nullable();
                $table->unsignedBigInteger('finalized_by')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'company_id', 'year', 'month']);
            });
        }

        if (! Schema::hasTable('cs_tax_pph23_transactions')) {
            Schema::create('cs_tax_pph23_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
                $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
                $table->string('source_type', 50);
                $table->unsignedBigInteger('source_id');
                $table->date('transaction_date');
                $table->unsignedSmallInteger('fiscal_year');
                $table->unsignedTinyInteger('fiscal_month');
                $table->string('vendor_npwp', 20)->nullable();
                $table->string('vendor_name', 300);
                $table->decimal('dpp_amount', 18, 2);
                $table->decimal('pph23_rate', 5, 2)->default(2);
                $table->decimal('pph23_amount', 18, 2);
                $table->unsignedBigInteger('ebupot_document_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_tax_ebupot_documents')) {
            Schema::create('cs_tax_ebupot_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->restrictOnDelete();
                $table->foreignId('company_id')->constrained('cs_core_companies')->restrictOnDelete();
                $table->char('public_id', 36)->unique();
                $table->foreignId('pph23_transaction_id')->constrained('cs_tax_pph23_transactions')->restrictOnDelete();
                $table->string('nomor_bupot', 30)->nullable();
                $table->enum('status', ['DRAFT', 'ISSUED', 'CANCELLED'])->default('DRAFT');
                $table->string('vendor_npwp', 20)->nullable();
                $table->string('vendor_name', 300);
                $table->decimal('dpp', 18, 2);
                $table->decimal('pph23', 18, 2);
                $table->date('tanggal_bupot');
                $table->string('djp_reference', 100)->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('cs_tax_pph23_transactions') && Schema::hasTable('cs_tax_ebupot_documents')) {
            Schema::table('cs_tax_pph23_transactions', function (Blueprint $table) {
                if (! $this->hasForeign('cs_tax_pph23_transactions', 'ebupot_document_id')) {
                    $table->foreign('ebupot_document_id')
                        ->references('id')->on('cs_tax_ebupot_documents')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cs_tax_pph23_transactions')) {
            Schema::table('cs_tax_pph23_transactions', fn (Blueprint $t) => $t->dropForeign(['ebupot_document_id']));
        }
        if (Schema::hasTable('cs_tax_ppn_transactions')) {
            Schema::table('cs_tax_ppn_transactions', fn (Blueprint $t) => $t->dropForeign(['efaktur_document_id']));
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

    protected function hasForeign(string $table, string $column): bool
    {
        $foreignKeys = Schema::getConnection()
            ->getSchemaBuilder()
            ->getForeignKeys($table);

        foreach ($foreignKeys as $fk) {
            if (in_array($column, $fk['columns'], true)) {
                return true;
            }
        }

        return false;
    }
};