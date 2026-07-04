<?php

namespace App\Modules\Business\Controllers\Api\V1;

use App\Modules\Business\Requests\CreateInvItemRequest;
use App\Modules\Business\Requests\CreateInvWarehouseRequest;
use App\Modules\Business\Requests\CreateStockMovementRequest;
use App\Modules\Business\Requests\UpdateInvItemRequest;
use App\Modules\Business\Requests\UpdateInvWarehouseRequest;
use App\Modules\Business\Resources\InvItemResource;
use App\Modules\Business\Resources\InvStockBalanceResource;
use App\Modules\Business\Resources\InvStockMovementResource;
use App\Modules\Business\Resources\InvWarehouseResource;
use App\Modules\Business\Services\InventoryService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class InventoryController extends Controller
{
    public function __construct(protected InventoryService $service) {}

    public function items(): JsonResponse
    {
        $items = $this->service->listItems(auth('api')->user(), request()->only([
            'search', 'is_active', 'per_page',
        ]));

        return ApiResponse::success(InvItemResource::collection($items));
    }

    public function showItem(string $publicId): JsonResponse
    {
        $item = $this->service->showItem(auth('api')->user(), $publicId);

        return ApiResponse::success(new InvItemResource($item));
    }

    public function storeItem(CreateInvItemRequest $request): JsonResponse
    {
        $item = $this->service->createItem(auth('api')->user(), $request->validated());

        return ApiResponse::success(new InvItemResource($item), 'Item created.', 201);
    }

    public function updateItem(UpdateInvItemRequest $request, string $publicId): JsonResponse
    {
        $item = $this->service->updateItem(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new InvItemResource($item), 'Item updated.');
    }

    public function destroyItem(string $publicId): JsonResponse
    {
        $this->service->deleteItem(auth('api')->user(), $publicId);

        return ApiResponse::success(null, 'Item deleted.');
    }

    public function warehouses(): JsonResponse
    {
        $warehouses = $this->service->listWarehouses(auth('api')->user(), request()->only([
            'is_active', 'per_page',
        ]));

        return ApiResponse::success(InvWarehouseResource::collection($warehouses));
    }

    public function showWarehouse(string $publicId): JsonResponse
    {
        $warehouse = $this->service->showWarehouse(auth('api')->user(), $publicId);

        return ApiResponse::success(new InvWarehouseResource($warehouse));
    }

    public function storeWarehouse(CreateInvWarehouseRequest $request): JsonResponse
    {
        $warehouse = $this->service->createWarehouse(auth('api')->user(), $request->validated());

        return ApiResponse::success(new InvWarehouseResource($warehouse), 'Warehouse created.', 201);
    }

    public function updateWarehouse(UpdateInvWarehouseRequest $request, string $publicId): JsonResponse
    {
        $warehouse = $this->service->updateWarehouse(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new InvWarehouseResource($warehouse), 'Warehouse updated.');
    }

    public function destroyWarehouse(string $publicId): JsonResponse
    {
        $this->service->deleteWarehouse(auth('api')->user(), $publicId);

        return ApiResponse::success(null, 'Warehouse deleted.');
    }

    public function balances(): JsonResponse
    {
        $balances = $this->service->listBalances(auth('api')->user(), request()->only([
            'warehouse_id', 'item_id', 'low_stock', 'per_page',
        ]));

        return ApiResponse::success(InvStockBalanceResource::collection($balances));
    }

    public function movements(): JsonResponse
    {
        $movements = $this->service->listMovements(auth('api')->user(), request()->only([
            'movement_type', 'per_page',
        ]));

        return ApiResponse::success(InvStockMovementResource::collection($movements));
    }

    public function storeMovement(CreateStockMovementRequest $request): JsonResponse
    {
        $movement = $this->service->createMovement(auth('api')->user(), $request->validated());

        return ApiResponse::success(new InvStockMovementResource($movement), 'Stock movement created.', 201);
    }
}