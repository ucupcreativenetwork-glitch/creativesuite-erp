<?php

namespace App\Modules\Auth\Controllers\Api\V1;

use App\Modules\Auth\Requests\TwoFactorConfirmRequest;
use App\Modules\Auth\Requests\TwoFactorDisableRequest;
use App\Modules\Auth\Requests\TwoFactorVerifyRequest;
use App\Modules\Auth\Resources\AuthTokenResource;
use App\Modules\Auth\Services\Contracts\TwoFactorServiceInterface;
use App\Modules\Core\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorServiceInterface $twoFactorService,
    ) {}

    public function setup(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $result = $this->twoFactorService->setup($user);

        return ApiResponse::success($result, 'Two-factor setup initiated.');
    }

    public function confirm(TwoFactorConfirmRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $result = $this->twoFactorService->confirm($user, $request->validated('code'));

        return ApiResponse::success($result, 'Two-factor authentication enabled.');
    }

    public function verify(TwoFactorVerifyRequest $request): JsonResponse
    {
        $result = $this->twoFactorService->verifyChallenge(
            $request->validated('mfa_token'),
            $request->validated('code'),
            $request->ip(),
        );

        return ApiResponse::success(
            new AuthTokenResource($result),
            'Two-factor verification successful.',
        );
    }

    public function disable(TwoFactorDisableRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $this->twoFactorService->disable($user, $request->validated('password'));

        return ApiResponse::success(null, 'Two-factor authentication disabled.');
    }

    public function regenerateRecoveryCodes(TwoFactorDisableRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $result = $this->twoFactorService->regenerateRecoveryCodes($user, $request->validated('password'));

        return ApiResponse::success($result, 'Recovery codes regenerated.');
    }
}