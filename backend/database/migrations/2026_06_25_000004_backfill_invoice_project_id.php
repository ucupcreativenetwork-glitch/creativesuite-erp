<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cs_fin_invoices') || ! Schema::hasColumn('cs_fin_invoices', 'project_id')) {
            return;
        }

        if (! Schema::hasTable('cs_sales_quotations') || ! Schema::hasColumn('cs_sales_quotations', 'project_id')) {
            return;
        }

        $invoices = DB::table('cs_fin_invoices')
            ->whereNull('project_id')
            ->whereNotNull('quotation_id')
            ->get(['id', 'quotation_id']);

        foreach ($invoices as $invoice) {
            $projectId = DB::table('cs_sales_quotations')
                ->where('id', $invoice->quotation_id)
                ->whereNotNull('project_id')
                ->value('project_id');

            if ($projectId) {
                DB::table('cs_fin_invoices')
                    ->where('id', $invoice->id)
                    ->update(['project_id' => $projectId]);
            }
        }
    }

    public function down(): void
    {
        // Data backfill — tidak di-rollback.
    }
};