<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cs_hr_employees') && ! Schema::hasColumn('cs_hr_employees', 'allowance_amount')) {
            Schema::table('cs_hr_employees', function (Blueprint $table): void {
                $table->decimal('allowance_amount', 18, 2)->default(0)->after('base_salary');
            });
        }

        if (Schema::hasTable('cs_int_connector_configs')) {
            Schema::table('cs_int_connector_configs', function (Blueprint $table): void {
                if (! Schema::hasColumn('cs_int_connector_configs', 'last_ingest_at')) {
                    $table->timestamp('last_ingest_at')->nullable()->after('is_active');
                }
                if (! Schema::hasColumn('cs_int_connector_configs', 'last_processed_count')) {
                    $table->unsignedInteger('last_processed_count')->default(0)->after('last_ingest_at');
                }
                if (! Schema::hasColumn('cs_int_connector_configs', 'last_error_count')) {
                    $table->unsignedInteger('last_error_count')->default(0)->after('last_processed_count');
                }
            });
        }

        if (! Schema::hasTable('cs_int_connector_ingest_logs')) {
            Schema::create('cs_int_connector_ingest_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('connector_id')->constrained('cs_int_connector_configs')->cascadeOnDelete();
                $table->unsignedInteger('processed')->default(0);
                $table->json('errors')->nullable();
                $table->string('payload_hash', 64)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['connector_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_int_connector_ingest_logs');

        if (Schema::hasTable('cs_int_connector_configs')) {
            Schema::table('cs_int_connector_configs', function (Blueprint $table): void {
                $columns = ['last_error_count', 'last_processed_count', 'last_ingest_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('cs_int_connector_configs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('cs_hr_employees') && Schema::hasColumn('cs_hr_employees', 'allowance_amount')) {
            Schema::table('cs_hr_employees', function (Blueprint $table): void {
                $table->dropColumn('allowance_amount');
            });
        }
    }
};