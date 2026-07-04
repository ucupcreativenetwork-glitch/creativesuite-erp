<?php

namespace App\Http\Middleware;

use App\Modules\Core\Models\Tenant;
use App\Support\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(
        protected TenantManager $tenantManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if ($user && $user->tenant_id) {
            $tenant = Tenant::query()->find($user->tenant_id);
            if ($tenant) {
                $this->tenantManager->set($tenant);
            }
        }

        return $next($request);
    }
}