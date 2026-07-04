<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_hr_employees', function (Blueprint $table): void {
            $table->string('ter_category', 1)->default('A')->after('base_salary');
            $table->string('bpjs_number', 20)->nullable()->after('ter_category');
        });

        Schema::create('cs_fin_bank_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('company_id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('bank_account_id');
            $table->date('transaction_date');
            $table->string('description', 500)->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->unsignedBigInteger('matched_payment_id')->nullable();
            $table->string('status', 20)->default('UNMATCHED');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'company_id', 'bank_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_fin_bank_statement_lines');

        Schema::table('cs_hr_employees', function (Blueprint $table): void {
            $table->dropColumn(['ter_category', 'bpjs_number']);
        });
    }
};