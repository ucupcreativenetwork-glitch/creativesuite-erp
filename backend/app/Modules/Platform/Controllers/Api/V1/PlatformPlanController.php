<?php

namespace App\Modules\Platform\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Resources\PlatformSubscriptionPlanResource;
use App\Modules\Platform\Services\PlatformPlanService;
use App\Support\Http\ApiResponse;

class PlatformPlanController extends Controller
{
    public function __construct(protected PlatformPlanService $service) {}

    public function index()
    {
        $plans = $this->service->list();

        return ApiResponse::success(PlatformSubscriptionPlanResource::collection($plans));
    }
}