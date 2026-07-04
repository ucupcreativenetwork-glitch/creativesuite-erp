<?php

namespace App\Http\Middleware;

use App\Support\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if (! $user || ! $user->is_platform_admin) {
            throw new ApiException('Platform administrator access required.', 403, 'FORBIDDEN');
        }

        return $next($request);
    }
}