<?php

namespace App\Modules\Integration\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Modules\Business\Resources\PurchaseOrderResource;
use App\Modules\Business\Services\PurchasingService;
use App\Modules\Integration\Requests\ExternalCreatePurchaseOrderRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;

class PurchasingController extends Controller
{
    public function __construct(protected PurchasingService $purchasingService) {}

    public function index(Request $request)
    {
        $user = auth('api')->user();

        return ApiResponse::success(
            PurchaseOrderResource::collection(
                $this->purchasingService->list($user, $request->only(['status', 'search', 'per_page'])),
            ),
        );
    }

    public function store(ExternalCreatePurchaseOrderRequest $request)
    {
        $po = $this->purchasingService->create(auth('api')->user(), $request->validated());

        return ApiResponse::success(new PurchaseOrderResource($po), 'Purchase order dibuat.');
    }
}