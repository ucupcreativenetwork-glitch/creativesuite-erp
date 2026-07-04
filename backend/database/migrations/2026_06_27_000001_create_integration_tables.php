<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_int_api_keys', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 36)->unique();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('key_prefix', 16);
            $table->string('key_hash', 64);
            $table->json('scopes');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('cs_int_webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 36)->unique();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('url', 500);
            $table->string('secret', 64);
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cs_int_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained('cs_int_webhook_endpoints')->cascadeOnDelete();
            $table->string('event', 80);
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('status', 20)->default('PENDING');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
        });

        Schema::create('cs_int_auto_reorder_rules', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 36)->unique();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignId('vendor_id')->nullable()->constrained('cs_crm_accounts')->nullOnDelete();
            $table->string('vendor_name', 200);
            $table->foreignId('warehouse_id')->constrained('cs_inv_warehouses')->cascadeOnDelete();
            $table->json('item_public_ids')->nullable();
            $table->decimal('order_multiplier', 8, 2)->default(1);
            $table->boolean('auto_submit')->default(false);
            $table->boolean('auto_approve')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cs_int_connector_configs', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 36)->unique();
            $table->foreignId('tenant_id')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('cs_core_companies')->cascadeOnDelete();
            $table->string('connector_type', 40);
            $table->string('name', 120);
            $table->string('ingest_token', 64)->unique();
            $table->string('employee_match_field', 40)->default('employee_number');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('cs_core_users')->nullOnDelete();
            $table->timestamps();
        });

        if (Schema::hasTable('cs_hr_attendance_records')) {
            Schema::table('cs_hr_attendance_records', function (Blueprint $table) {
                if (! Schema::hasColumn('cs_hr_attendance_records', 'source')) {
                    $table->string('source', 40)->nullable()->after('notes');
                }
                if (! Schema::hasColumn('cs_hr_attendance_records', 'external_ref')) {
                    $table->string('external_ref', 120)->nullable()->after('source');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cs_hr_attendance_records')) {
            Schema::table('cs_hr_attendance_records', function (Blueprint $table) {
                if (Schema::hasColumn('cs_hr_attendance_records', 'external_ref')) {
                    $table->dropColumn('external_ref');
                }
                if (Schema::hasColumn('cs_hr_attendance_records', 'source')) {
                    $table->dropColumn('source');
                }
            });
        }

        Schema::dropIfExists('cs_int_connector_configs');
        Schema::dropIfExists('cs_int_auto_reorder_rules');
        Schema::dropIfExists('cs_int_webhook_deliveries');
        Schema::dropIfExists('cs_int_webhook_endpoints');
        Schema::dropIfExists('cs_int_api_keys');
    }
};