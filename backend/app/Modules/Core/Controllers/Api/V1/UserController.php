<?php

namespace App\Modules\Core\Controllers\Api\V1;

use App\Modules\Auth\Resources\UserResource;
use App\Modules\Core\Requests\AssignUserRolesRequest;
use App\Modules\Core\Requests\ListUsersRequest;
use App\Modules\Core\Requests\UpdateUserRequest;
use App\Modules\Core\Services\UserService;
use App\Modules\Iam\Services\UserRevokeService;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        protected UserService $userService,
        protected UserRevokeService $revokeService,
    ) {}

    public function index(ListUsersRequest $request): JsonResponse
    {
        $users = $this->userService->list(auth('api')->user(), $request->validated());

        return ApiResponse::success(UserResource::collection($users));
    }

    public function show(string $publicId): JsonResponse
    {
        $user = $this->userService->show(auth('api')->user(), $publicId);

        return ApiResponse::success(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, string $publicId): JsonResponse
    {
        $user = $this->userService->update(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new UserResource($user), 'User updated.');
    }

    public function assignRoles(AssignUserRolesRequest $request, string $publicId): JsonResponse
    {
        $user = $this->userService->assignRoles(
            auth('api')->user(),
            $publicId,
            $request->validated('role_ids'),
        );

        return ApiResponse::success(new UserResource($user), 'Roles assigned.');
    }

    public function destroy(string $publicId): JsonResponse
    {
        $actor = auth('api')->user();

        if (! $actor->isTenantAdministrator()) {
            throw new ApiException('Hanya administrator yang dapat menghapus user.', 403, 'ADMIN_ONLY');
        }

        $target = \App\Modules\Core\Models\User::query()
            ->where('public_id', $publicId)
            ->where('tenant_id', $actor->tenant_id)
            ->firstOrFail();

        $user = $target->provisioning_source === 'REQUEST_APPROVAL'
            ? $this->revokeService->revoke($actor, $publicId)
            : $this->userService->disable($actor, $publicId);

        return ApiResponse::success(new UserResource($user), 'User berhasil dihapus.');
    }
}