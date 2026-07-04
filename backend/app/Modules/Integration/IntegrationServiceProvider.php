<?php

namespace App\Modules\Integration;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->name('api.v1.')
            ->group(__DIR__.'/Routes/api_v1.php');

        Route::prefix('api/v1')
            ->middleware('api')
            ->name('api.v1.')
            ->group(__DIR__.'/Routes/external_v1.php');
    }
}