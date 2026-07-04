<?php

namespace App\Modules\Core\Controllers\Api\V1;

use App\Modules\Auth\Resources\RoleResource;
use App\Modules\Core\Models\Role;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RoleController extends Controller
{
    use ChecksPermissions;

    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        $this->assertPermission($user, 'core.role.read');

        $roles = Role::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(RoleResource::collection($roles));
    }

    public function show(string $code): JsonResponse
    {
        $user = auth('api')->user();
        $this->assertPermission($user, 'core.role.read');

        $role = Role::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('code', $code)
            ->with('permissions')
            ->first();

        if (! $role) {
            throw new ApiException('Role not found.', 404, 'ROLE_NOT_FOUND');
        }

        return ApiResponse::success(new RoleResource($role));
    }
}