<?php

namespace App\Modules\Auth\Controllers\Api\V1;

use App\Modules\Auth\Requests\RegisterCompanyRequest;
use App\Modules\Auth\Requests\RegisterUserRequest;
use App\Modules\Auth\Resources\AuthTokenResource;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Auth\Services\Contracts\RegistrationServiceInterface;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RegisterController extends Controller
{
    public function __construct(
        protected RegistrationServiceInterface $registrationService,
    ) {}

    public function registerCompany(RegisterCompanyRequest $request): JsonResponse
    {
        $result = $this->registrationService->registerCompany($request->validated());

        return ApiResponse::success(
            new AuthTokenResource($result),
            'Company registered successfully.',
            201,
        );
    }

    public function registerUser(RegisterUserRequest $request): JsonResponse
    {
        $result = $this->registrationService->registerUser($request->validated());

        return ApiResponse::success(
            ['user' => new UserResource($result['user'])],
            'User registered successfully.',
            201,
        );
    }
}