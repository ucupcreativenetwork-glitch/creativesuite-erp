<?php

namespace App\Modules\Iam\Controllers\Api\V1;

use App\Modules\Auth\Resources\UserResource;
use App\Modules\Iam\Services\UserRevokeService;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class UserRevokeController extends Controller
{
    use ChecksPermissions;

    public function __construct(protected UserRevokeService $service) {}

    public function revoke(string $publicId): JsonResponse
    {
        $actor = auth('api')->user();

        $this->assertPermission($actor, 'iam.user.revoke');

        $user = $this->service->revoke($actor, $publicId);

        return ApiResponse::success(new UserResource($user), 'Akses user dicabut.');
    }
}