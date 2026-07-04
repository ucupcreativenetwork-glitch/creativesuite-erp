<?php

namespace App\Modules\Business;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BusinessServiceProvider extends ServiceProvider
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
    }
}