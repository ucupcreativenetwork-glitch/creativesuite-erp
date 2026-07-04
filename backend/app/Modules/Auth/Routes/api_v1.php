<?php

use App\Modules\Core\Controllers\Api\V1\HealthController;
use App\Modules\Auth\Controllers\Api\V1\AuthController;
use App\Modules\Auth\Controllers\Api\V1\EmailVerificationController;
use App\Modules\Auth\Controllers\Api\V1\PasswordController;
use App\Modules\Auth\Controllers\Api\V1\RegisterController;
use App\Modules\Auth\Controllers\Api\V1\TwoFactorController;
use App\Modules\Auth\Controllers\Api\V1\UserActivationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HealthController::class, 'index'])->name('root');
Route::get('/health', [HealthController::class, 'index'])->name('health');

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('register/company', [RegisterController::class, 'registerCompany'])
        ->middleware('throttle:5,1')
        ->name('register.company');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');
    Route::post('forgot-password', [PasswordController::class, 'forgot'])
        ->middleware('throttle:5,1')
        ->name('password.forgot');
    Route::post('reset-password', [PasswordController::class, 'reset'])
        ->middleware('throttle:5,1')
        ->name('password.reset');
    Route::post('two-factor/verify', [TwoFactorController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('two-factor.verify');

    Route::prefix('activation')->name('activation.')->middleware('throttle:30,1')->group(function (): void {
        Route::post('validate', [UserActivationController::class, 'validateToken'])->name('validate');
        Route::post('set-password', [UserActivationController::class, 'setPassword'])->name('set-password');
        Route::post('verify-otp', [UserActivationController::class, 'verifyOtp'])->name('verify-otp');
        Route::post('resend-otp', [UserActivationController::class, 'resendOtp'])->name('resend-otp');
    });

    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware(['auth:api', 'company.context'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        Route::post('register/user', [RegisterController::class, 'registerUser'])->name('register.user');

        Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        Route::post('change-password', [PasswordController::class, 'change'])
            ->middleware('throttle:10,1')
            ->name('password.change');

        Route::prefix('two-factor')->name('two-factor.')->group(function (): void {
            Route::post('setup', [TwoFactorController::class, 'setup'])->name('setup');
            Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
            Route::post('disable', [TwoFactorController::class, 'disable'])->name('disable');
            Route::post('recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes');
        });
    });
});