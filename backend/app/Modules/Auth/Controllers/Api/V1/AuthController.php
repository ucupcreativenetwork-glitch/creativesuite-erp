<?php

namespace App\Modules\Auth\Controllers\Api\V1;

use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Resources\AuthTokenResource;
use App\Modules\Auth\Resources\BranchResource;
use App\Modules\Auth\Resources\CompanyResource;
use App\Modules\Auth\Resources\TenantResource;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Auth\Services\Contracts\AuthServiceInterface;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    public function __construct(
        protected AuthServiceInterface $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->companyIdentifier(),
            $request->validated('email'),
            $request->validated('password'),
            $request->ip(),
        );

        $message = ($result['mfa_required'] ?? false)
            ? 'Two-factor authentication required.'
            : 'Login successful.';

        return ApiResponse::success(
            new AuthTokenResource($result),
            $message,
        );
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    public function refresh(): JsonResponse
    {
        $result = $this->authService->refresh();

        return ApiResponse::success(
            new AuthTokenResource($result),
            'Token refreshed successfully.',
        );
    }

    public function me(): JsonResponse
    {
        $result = $this->authService->me();

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'tenant' => $result['tenant'] ? new TenantResource($result['tenant']) : null,
            'company' => $result['company'] ? new CompanyResource($result['company']) : null,
            'branch' => $result['branch'] ? new BranchResource($result['branch']) : null,
        ]);
    }
}