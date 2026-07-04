<?php

namespace App\Modules\Business\Controllers\Api\V1;

use App\Modules\Business\Services\BusinessReportService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BusinessReportController extends Controller
{
    public function __construct(protected BusinessReportService $service) {}

    public function dashboard(): JsonResponse
    {
        $stats = $this->service->dashboard(auth('api')->user());

        return ApiResponse::success($stats);
    }
}
