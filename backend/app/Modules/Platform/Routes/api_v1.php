<?php

use App\Modules\Platform\Controllers\Api\V1\PlatformDashboardController;
use App\Modules\Platform\Controllers\Api\V1\PlatformMaintenanceController;
use App\Modules\Platform\Controllers\Api\V1\PlatformPlanController;
use App\Modules\Platform\Controllers\Api\V1\PlatformTenantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'company.context', 'platform.admin'])->prefix('platform')->name('platform.')->group(function (): void {
    Route::get('/dashboard', [PlatformDashboardController::class, 'index'])->name('dashboard');
    Route::get('/plans', [PlatformPlanController::class, 'index'])->name('plans.index');
    Route::post('/purge-demo', [PlatformMaintenanceController::class, 'purgeDemo'])->name('purge-demo');
    Route::post('/seed-demo', [PlatformMaintenanceController::class, 'seedDemo'])->name('seed-demo');

    Route::prefix('tenants')->name('tenants.')->group(function (): void {
        Route::get('/', [PlatformTenantController::class, 'index'])->name('index');
        Route::get('/{publicId}', [PlatformTenantController::class, 'show'])->name('show');
        Route::patch('/{publicId}', [PlatformTenantController::class, 'update'])->name('update');
        Route::post('/{publicId}/suspend', [PlatformTenantController::class, 'suspend'])->name('suspend');
        Route::post('/{publicId}/activate', [PlatformTenantController::class, 'activate'])->name('activate');
        Route::delete('/{publicId}', [PlatformTenantController::class, 'destroy'])->name('destroy');
    });
});