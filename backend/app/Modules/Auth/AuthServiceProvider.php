<?php

namespace App\Modules\Auth;

use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\Contracts\AuthServiceInterface;
use App\Modules\Auth\Services\Contracts\EmailVerificationServiceInterface;
use App\Modules\Auth\Services\Contracts\PasswordResetServiceInterface;
use App\Modules\Auth\Services\Contracts\RegistrationServiceInterface;
use App\Modules\Auth\Services\Contracts\TwoFactorServiceInterface;
use App\Modules\Auth\Services\EmailVerificationService;
use App\Modules\Auth\Services\PasswordResetService;
use App\Modules\Auth\Services\RegistrationService;
use App\Modules\Auth\Services\TwoFactorService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(RegistrationServiceInterface::class, RegistrationService::class);
        $this->app->bind(PasswordResetServiceInterface::class, PasswordResetService::class);
        $this->app->bind(EmailVerificationServiceInterface::class, EmailVerificationService::class);
        $this->app->bind(TwoFactorServiceInterface::class, TwoFactorService::class);
    }

    public function boot(): void
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->name('api.v1.')
            ->group(__DIR__.'/Routes/api_v1.php');
    }
}