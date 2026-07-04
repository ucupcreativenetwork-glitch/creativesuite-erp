<?php

namespace App\Modules\Business\Controllers\Api\V1;

use App\Modules\Business\Requests\CreatePurchaseOrderRequest;
use App\Modules\Business\Requests\ReceivePurchaseOrderRequest;
use App\Modules\Business\Requests\UpdatePurchaseOrderRequest;
use App\Modules\Business\Resources\PurchaseOrderResource;
use App\Modules\Business\Services\PurchasingService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PurchasingController extends Controller
{
    public function __construct(protected PurchasingService $service) {}

    public function index(): JsonResponse
    {
        $orders = $this->service->list(auth('api')->user(), request()->only([
            'status', 'search', 'per_page',
        ]));

        return ApiResponse::success(PurchaseOrderResource::collection($orders));
    }

    public function show(string $publicId): JsonResponse
    {
        $order = $this->service->show(auth('api')->user(), $publicId);

        return ApiResponse::success(new PurchaseOrderResource($order));
    }

    public function store(CreatePurchaseOrderRequest $request): JsonResponse
    {
        $order = $this->service->create(auth('api')->user(), $request->validated());

        return ApiResponse::success(new PurchaseOrderResource($order), 'Purchase order created.', 201);
    }

    public function update(UpdatePurchaseOrderRequest $request, string $publicId): JsonResponse
    {
        $order = $this->service->update(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new PurchaseOrderResource($order), 'Purchase order updated.');
    }

    public function destroy(string $publicId): JsonResponse
    {
        $this->service->delete(auth('api')->user(), $publicId);

        return ApiResponse::success(null, 'Purchase order deleted.');
    }

    public function submit(string $publicId): JsonResponse
    {
        $order = $this->service->submit(auth('api')->user(), $publicId);

        return ApiResponse::success(new PurchaseOrderResource($order), 'Purchase order submitted.');
    }

    public function approve(string $publicId): JsonResponse
    {
        $order = $this->service->approve(auth('api')->user(), $publicId);

        return ApiResponse::success(new PurchaseOrderResource($order), 'Purchase order approved.');
    }

    public function receive(ReceivePurchaseOrderRequest $request, string $publicId): JsonResponse
    {
        $order = $this->service->receive(
            auth('api')->user(),
            $publicId,
            $request->validated('warehouse_id'),
        );

        return ApiResponse::success(new PurchaseOrderResource($order), 'Purchase order received.');
    }
}