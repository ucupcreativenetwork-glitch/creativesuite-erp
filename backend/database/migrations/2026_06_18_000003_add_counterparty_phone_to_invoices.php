<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_fin_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('cs_fin_invoices', 'counterparty_phone')) {
                $table->string('counterparty_phone', 20)->nullable()->after('counterparty_npwp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cs_fin_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('cs_fin_invoices', 'counterparty_phone')) {
                $table->dropColumn('counterparty_phone');
            }
        });
    }
};