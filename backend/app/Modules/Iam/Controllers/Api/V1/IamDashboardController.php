<?php

namespace App\Modules\Iam\Controllers\Api\V1;

use App\Modules\Iam\Services\IamDashboardService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class IamDashboardController extends Controller
{
    public function __construct(protected IamDashboardService $service) {}

    public function head(): JsonResponse
    {
        return ApiResponse::success($this->service->headStats(auth('api')->user()));
    }

    public function approver(): JsonResponse
    {
        return ApiResponse::success($this->service->approverStats(auth('api')->user()));
    }

    public function owner(): JsonResponse
    {
        return ApiResponse::success($this->service->ownerStats(auth('api')->user()));
    }
}