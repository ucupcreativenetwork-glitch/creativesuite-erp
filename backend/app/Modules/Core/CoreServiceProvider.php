<?php

namespace App\Modules\Core;

use App\Modules\Core\Repositories\BranchRepository;
use App\Modules\Core\Repositories\CompanyRepository;
use App\Modules\Core\Repositories\Contracts\BranchRepositoryInterface;
use App\Modules\Core\Repositories\Contracts\CompanyRepositoryInterface;
use App\Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use App\Modules\Core\Repositories\Contracts\TenantRepositoryInterface;
use App\Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\Core\Repositories\RoleRepository;
use App\Modules\Core\Repositories\TenantRepository;
use App\Modules\Core\Repositories\UserRepository;
use App\Support\Tenant\CompanyContextResolver;
use App\Support\Tenant\CompanyManager;
use App\Support\Tenant\TenantManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
        $this->app->singleton(CompanyManager::class);
        $this->app->singleton(CompanyContextResolver::class);

        $this->app->bind(TenantRepositoryInterface::class, TenantRepository::class);
        $this->app->bind(CompanyRepositoryInterface::class, CompanyRepository::class);
        $this->app->bind(BranchRepositoryInterface::class, BranchRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
    }

    public function boot(): void
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->name('api.v1.')
            ->group(__DIR__.'/Routes/api_v1.php');
    }
}