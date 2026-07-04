<?php

namespace App\Modules\Auth\Controllers\Api\V1;

use App\Modules\Auth\Services\Contracts\EmailVerificationServiceInterface;
use App\Modules\Core\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmailVerificationController extends Controller
{
    public function __construct(
        protected EmailVerificationServiceInterface $emailVerificationService,
    ) {}

    public function send(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $this->emailVerificationService->sendVerificationNotification($user);

        return ApiResponse::success(null, 'Verification link sent.');
    }

    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::query()->where('public_id', $id)->firstOrFail();

        $message = $this->emailVerificationService->verify($user, $hash);

        return ApiResponse::success(null, $message);
    }
}