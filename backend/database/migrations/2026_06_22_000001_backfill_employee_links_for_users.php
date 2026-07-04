<?php

use App\Modules\Business\Services\EmployeeLinkService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(EmployeeLinkService::class)) {
            return;
        }

        app(EmployeeLinkService::class)->syncAllActiveUsers();
    }

    public function down(): void
    {
        //
    }
};