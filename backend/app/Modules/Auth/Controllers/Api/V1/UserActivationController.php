<?php

namespace App\Modules\Auth\Controllers\Api\V1;

use App\Modules\Auth\Requests\ActivationSetPasswordRequest;
use App\Modules\Auth\Requests\ActivationVerifyOtpRequest;
use App\Modules\Auth\Services\UserActivationService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserActivationController extends Controller
{
    public function __construct(protected UserActivationService $service) {}

    public function validateToken(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'size:64']]);

        return ApiResponse::success($this->service->validateToken($request->input('token')));
    }

    public function setPassword(ActivationSetPasswordRequest $request): JsonResponse
    {
        $data = $this->service->setPassword(
            $request->validated('token'),
            $request->validated('password'),
        );

        return ApiResponse::success($data, 'Password disimpan. Kode OTP telah dikirim ke email Anda.');
    }

    public function verifyOtp(ActivationVerifyOtpRequest $request): JsonResponse
    {
        $data = $this->service->verifyOtpAndActivate(
            $request->validated('token'),
            $request->validated('otp_session_token'),
            $request->validated('otp'),
        );

        return ApiResponse::success($data, 'Akun berhasil diaktifkan.');
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'otp_session_token' => ['required', 'string'],
        ]);

        $data = $this->service->resendOtp(
            $request->input('token'),
            $request->input('otp_session_token'),
        );

        return ApiResponse::success($data, 'OTP baru telah dikirim.');
    }
}