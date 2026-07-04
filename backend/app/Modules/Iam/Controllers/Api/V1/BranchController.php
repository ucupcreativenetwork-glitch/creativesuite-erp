<?php

namespace App\Modules\Iam\Controllers\Api\V1;

use App\Modules\Iam\Requests\UpdateBranchRequest;
use App\Modules\Iam\Resources\BranchResource;
use App\Modules\Iam\Services\BranchService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BranchController extends Controller
{
    public function __construct(protected BranchService $service) {}

    public function index(): JsonResponse
    {
        $branches = $this->service->list(auth('api')->user());

        return ApiResponse::success(BranchResource::collection($branches));
    }

    public function show(int $branchId): JsonResponse
    {
        $branch = $this->service->show(auth('api')->user(), $branchId);

        return ApiResponse::success(new BranchResource($branch));
    }

    public function update(UpdateBranchRequest $request, int $branchId): JsonResponse
    {
        $branch = $this->service->update(
            auth('api')->user(),
            $branchId,
            $request->validated(),
        );

        return ApiResponse::success(new BranchResource($branch), 'Pengaturan cabang disimpan.');
    }
}