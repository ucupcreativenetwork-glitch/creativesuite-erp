<?php

namespace App\Modules\Platform\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\PlatformTenantService;
use App\Support\Http\ApiResponse;

class PlatformDashboardController extends Controller
{
    public function __construct(protected PlatformTenantService $service) {}

    public function index()
    {
        return ApiResponse::success($this->service->dashboard());
    }
}