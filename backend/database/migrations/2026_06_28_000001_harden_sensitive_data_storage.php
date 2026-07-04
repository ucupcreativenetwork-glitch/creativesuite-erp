<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_core_companies', function (Blueprint $table) {
            $table->text('npwp')->nullable()->change();
            $table->text('nitku')->nullable()->change();
            $table->text('phone')->nullable()->change();
        });

        Schema::table('cs_core_users', function (Blueprint $table) {
            $table->text('phone')->nullable()->change();
        });

        Schema::table('cs_hr_employees', function (Blueprint $table) {
            $table->text('bpjs_number')->nullable()->change();
            $table->text('phone')->nullable()->change();
        });

        Schema::table('cs_crm_accounts', function (Blueprint $table) {
            $table->text('npwp')->nullable()->change();
            $table->text('phone')->nullable()->change();
        });

        Schema::table('cs_int_webhook_endpoints', function (Blueprint $table) {
            $table->text('secret')->change();
        });

        Schema::table('cs_core_user_verification_otps', function (Blueprint $table) {
            $table->string('otp_code', 255)->change();
            $table->string('session_token', 64)->nullable()->change();
        });

        DB::table('password_reset_tokens')->delete();

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropPrimary(['email']);
            $table->foreignId('tenant_id')->after('email')->constrained('cs_platform_tenants')->cascadeOnDelete();
            $table->primary(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropPrimary(['tenant_id', 'email']);
            $table->dropColumn('tenant_id');
            $table->string('email')->primary()->change();
        });

        Schema::table('cs_core_user_verification_otps', function (Blueprint $table) {
            $table->string('otp_code', 6)->change();
        });
    }
};