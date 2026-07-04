<?php

use App\Modules\Integration\Controllers\Api\External\AttendanceController;
use App\Modules\Integration\Controllers\Api\External\ConnectorPushController;
use App\Modules\Integration\Controllers\Api\External\InventoryController;
use App\Modules\Integration\Controllers\Api\External\PurchasingController;
use Illuminate\Support\Facades\Route;

Route::prefix('external')->name('external.')->group(function (): void {
    Route::post('/connectors/push', [ConnectorPushController::class, 'push'])
        ->middleware('throttle:30,1')
        ->name('connectors.push');

    Route::middleware('api.key')->group(function (): void {
        Route::prefix('attendance')->name('attendance.')->middleware('api.key:attendance.write')->group(function (): void {
            Route::post('/import', [AttendanceController::class, 'import'])->name('import');
            Route::post('/clock-in', [AttendanceController::class, 'clockIn'])->name('clock-in');
            Route::post('/clock-out', [AttendanceController::class, 'clockOut'])->name('clock-out');
        });

        Route::get('/attendance', [AttendanceController::class, 'index'])
            ->middleware('api.key:attendance.read')
            ->name('attendance.index');

        Route::prefix('purchasing')->name('purchasing.')->group(function (): void {
            Route::get('/orders', [PurchasingController::class, 'index'])
                ->middleware('api.key:purchasing.read')
                ->name('orders.index');
            Route::post('/orders', [PurchasingController::class, 'store'])
                ->middleware('api.key:purchasing.write')
                ->name('orders.store');
        });

        Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])
            ->middleware('api.key:inventory.read')
            ->name('inventory.low-stock');
    });
});