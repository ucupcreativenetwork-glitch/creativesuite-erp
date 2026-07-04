<?php

use App\Modules\Core\Controllers\Api\V1\CompanyController;
use App\Modules\Core\Controllers\Api\V1\RoleController;
use App\Modules\Core\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'company.context'])->group(function (): void {
    Route::prefix('companies')->name('companies.')->group(function (): void {
        Route::get('/{publicId}', [CompanyController::class, 'show'])->name('show');
        Route::put('/{publicId}', [CompanyController::class, 'update'])->name('update');
        Route::post('/{publicId}/logo', [CompanyController::class, 'uploadLogo'])->name('logo.upload');
    });

    Route::prefix('users')->name('users.')->group(function (): void {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/{publicId}', [UserController::class, 'show'])->name('show');
        Route::put('/{publicId}', [UserController::class, 'update'])->name('update');
        Route::put('/{publicId}/roles', [UserController::class, 'assignRoles'])->name('roles.assign');
        Route::delete('/{publicId}', [UserController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('roles')->name('roles.')->group(function (): void {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('/{code}', [RoleController::class, 'show'])->name('show');
    });
});