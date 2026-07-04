<?php

namespace App\Modules\Iam\Controllers\Api\V1;

use App\Modules\Iam\Services\IamFormOptionsService;
use App\Support\Business\ChecksPermissions;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IamFormOptionsController extends Controller
{
    use ChecksPermissions;

    public function __construct(protected IamFormOptionsService $service) {}

    public function show(Request $request): JsonResponse
    {
        $actor = auth('api')->user();
        $this->assertPermission($actor, 'iam.request.create');

        $options = $this->service->resolve($actor, $request->query('department_id'));

        return ApiResponse::success($options);
    }
}