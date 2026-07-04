<?php

namespace App\Modules\Business\Controllers\Api\V1;

use App\Modules\Business\Requests\AssignWorkOrderRequest;
use App\Modules\Business\Requests\CreateWorkOrderRequest;
use App\Modules\Business\Requests\UpdateWorkOrderRequest;
use App\Modules\Business\Resources\WorkOrderResource;
use App\Modules\Business\Services\WorkOrderService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class WorkOrderController extends Controller
{
    public function __construct(protected WorkOrderService $service) {}

    public function index(): JsonResponse
    {
        $workOrders = $this->service->list(auth('api')->user(), request()->only([
            'status', 'search', 'per_page',
        ]));

        return ApiResponse::success(WorkOrderResource::collection($workOrders));
    }

    public function show(string $publicId): JsonResponse
    {
        $workOrder = $this->service->show(auth('api')->user(), $publicId);

        return ApiResponse::success(new WorkOrderResource($workOrder));
    }

    public function store(CreateWorkOrderRequest $request): JsonResponse
    {
        $workOrder = $this->service->create(auth('api')->user(), $request->validated());

        return ApiResponse::success(new WorkOrderResource($workOrder), 'Work order created.', 201);
    }

    public function update(UpdateWorkOrderRequest $request, string $publicId): JsonResponse
    {
        $workOrder = $this->service->update(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new WorkOrderResource($workOrder), 'Work order updated.');
    }

    public function destroy(string $publicId): JsonResponse
    {
        $this->service->delete(auth('api')->user(), $publicId);

        return ApiResponse::success(null, 'Work order deleted.');
    }

    public function assign(AssignWorkOrderRequest $request, string $publicId): JsonResponse
    {
        $workOrder = $this->service->assign(
            auth('api')->user(),
            $publicId,
            $request->validated('technician_id'),
        );

        return ApiResponse::success(new WorkOrderResource($workOrder), 'Work order assigned.');
    }

    public function complete(string $publicId): JsonResponse
    {
        $workOrder = $this->service->complete(auth('api')->user(), $publicId);

        return ApiResponse::success(new WorkOrderResource($workOrder), 'Work order completed.');
    }
}