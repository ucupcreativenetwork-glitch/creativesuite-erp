<?php

namespace App\Modules\Finance\Controllers\Api\V1;

use App\Modules\Finance\Requests\CreateCoaRequest;
use App\Modules\Finance\Requests\UpdateCoaRequest;
use App\Modules\Finance\Resources\ChartOfAccountResource;
use App\Modules\Finance\Services\CoaService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CoaController extends Controller
{
    public function __construct(protected CoaService $coaService) {}

    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        $accounts = $this->coaService->list($user, request()->only(['is_active', 'category']));

        return ApiResponse::success(ChartOfAccountResource::collection($accounts));
    }

    public function tree(): JsonResponse
    {
        $user = auth('api')->user();

        return ApiResponse::success($this->coaService->tree($user));
    }

    public function store(CreateCoaRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $account = $this->coaService->create($user, $request->validated());

        return ApiResponse::success(new ChartOfAccountResource($account), 'COA created.', 201);
    }

    public function update(UpdateCoaRequest $request, string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $account = $this->coaService->update($user, $publicId, $request->validated());

        return ApiResponse::success(new ChartOfAccountResource($account), 'COA updated.');
    }
}