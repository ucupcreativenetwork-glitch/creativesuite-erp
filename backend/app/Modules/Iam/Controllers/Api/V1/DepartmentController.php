<?php

namespace App\Modules\Iam\Controllers\Api\V1;

use App\Modules\Iam\Models\Department;
use App\Modules\Iam\Resources\DepartmentResource;
use App\Modules\Iam\Services\DepartmentRoleGuard;
use App\Support\Business\ChecksPermissions;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class DepartmentController extends Controller
{
    use ChecksPermissions;

    public function index(): JsonResponse
    {
        $actor = auth('api')->user();
        $this->assertPermission($actor, 'iam.department.read');

        $departments = Department::query()
            ->where('company_id', $actor->default_company_id)
            ->where('is_active', true)
            ->with('allowedRoles')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(DepartmentResource::collection($departments));
    }

    public function allowedRoles(string $publicId, DepartmentRoleGuard $guard): JsonResponse
    {
        $actor = auth('api')->user();
        $this->assertPermission($actor, 'iam.request.create');

        $department = Department::query()->where('public_id', $publicId)->firstOrFail();

        $headDept = $guard->resolveDepartmentForHead($actor->id, $actor->default_company_id);
        if (! $headDept || $headDept->id !== $department->id) {
            $this->assertPermission($actor, 'iam.department.read');
        }

        $department->load('allowedRoles');

        return ApiResponse::success(new DepartmentResource($department));
    }

    public function myDepartment(DepartmentRoleGuard $guard): JsonResponse
    {
        $actor = auth('api')->user();
        $this->assertPermission($actor, 'iam.request.create');

        $department = $guard->resolveDepartmentForHead($actor->id, $actor->default_company_id);
        if (! $department) {
            return ApiResponse::error('Anda bukan Head Divisi.', 404, 'NOT_DEPARTMENT_HEAD');
        }

        $department->load('allowedRoles');

        return ApiResponse::success(new DepartmentResource($department));
    }
}