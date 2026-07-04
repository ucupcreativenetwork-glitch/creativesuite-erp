<?php

namespace App\Http\Middleware;

use App\Modules\Core\Models\User;
use App\Support\Tenant\CompanyContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    public function __construct(
        protected CompanyContextResolver $companyContextResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if ($user instanceof User) {
            if ($user->is_platform_admin && $request->is('api/v1/platform/*')) {
                return $next($request);
            }

            $this->companyContextResolver->resolveActiveCompanyId();
        }

        return $next($request);
    }
}