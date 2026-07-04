<?php

use App\Http\Middleware\SetTenantContext;
use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            SetTenantContext::class,
        ]);

        $middleware->alias([
            'platform.admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'api.key' => \App\Http\Middleware\AuthenticateApiKey::class,
            'company.context' => \App\Http\Middleware\SetCompanyContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Expected business-rule errors (validation, forbidden, etc.) — not server faults.
        $exceptions->dontReport(ApiException::class);

        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    $e->getMessage(),
                    $e->statusCode,
                    $e->errorCode,
                    $e->errors,
                );
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    'Validation failed.',
                    422,
                    'VALIDATION_ERROR',
                    $e->errors(),
                );
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    'Unauthenticated.',
                    401,
                    'UNAUTHENTICATED',
                );
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    'Resource not found.',
                    404,
                    'NOT_FOUND',
                );
            }
        });
    })->create();