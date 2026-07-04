<?php

namespace App\Modules\Iam\Controllers\Api\V1;

use App\Modules\Iam\Resources\DepartmentResource;
use App\Modules\Iam\Services\DepartmentRoleMappingService;
use App\Support\Business\ChecksPermissions;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DepartmentRoleMappingController extends Controller
{
    use ChecksPermissions;

    public function __construct(protected DepartmentRoleMappingService $service) {}

    public function index(): JsonResponse
    {
        $actor = auth('api')->user();
        $this->assertPermission($actor, 'iam.department_role.manage');

        return ApiResponse::success(
            $this->service->listForCompany($actor->tenant_id, $actor->default_company_id),
        );
    }

    public function update(Request $request, string $publicId): JsonResponse
    {
        $actor = auth('api')->user();
        $this->assertPermission($actor, 'iam.department_role.manage');

        $data = $request->validate([
            'role_ids' => ['required', 'array'],
            'role_ids.*' => ['integer', 'exists:cs_core_roles,id'],
        ]);

        $department = $this->service->sync(
            $actor->tenant_id,
            $actor->default_company_id,
            $publicId,
            $data['role_ids'],
        );

        $department->load('allowedRoles');

        return ApiResponse::success(new DepartmentResource($department), 'Mapping departemen-role diperbarui.');
    }
}