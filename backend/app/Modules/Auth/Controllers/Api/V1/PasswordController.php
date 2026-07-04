<?php

namespace App\Modules\Auth\Controllers\Api\V1;

use App\Modules\Auth\Requests\ChangePasswordRequest;
use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use App\Modules\Auth\Services\ChangePasswordService;
use App\Modules\Auth\Services\Contracts\PasswordResetServiceInterface;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PasswordController extends Controller
{
    public function __construct(
        protected PasswordResetServiceInterface $passwordResetService,
        protected ChangePasswordService $changePasswordService,
    ) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $message = $this->passwordResetService->sendResetLink(
            $request->companyIdentifier(),
            $request->validated('email'),
        );

        return ApiResponse::success(null, $message);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $message = $this->passwordResetService->resetPassword(
            $request->companyIdentifier(),
            $request->validated('email'),
            $request->validated('token'),
            $request->validated('password'),
        );

        return ApiResponse::success(null, $message);
    }

    public function change(ChangePasswordRequest $request): JsonResponse
    {
        $this->changePasswordService->change(
            auth('api')->user(),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return ApiResponse::success(null, 'Password berhasil diubah.');
    }
}