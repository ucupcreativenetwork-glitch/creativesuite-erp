<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_prj_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('company_id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('entry_date');
            $table->decimal('hours', 8, 2);
            $table->decimal('hourly_cost', 18, 2)->default(0);
            $table->boolean('is_billable')->default(true);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'company_id', 'project_id', 'entry_date']);
        });

        Schema::create('cs_prj_milestones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('company_id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('project_id');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->decimal('amount', 18, 2);
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'company_id', 'project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_prj_milestones');
        Schema::dropIfExists('cs_prj_time_entries');
    }
};