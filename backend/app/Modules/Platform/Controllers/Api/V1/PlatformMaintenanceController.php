<?php

namespace App\Modules\Platform\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\PlatformTenantService;
use App\Support\Http\ApiResponse;

class PlatformMaintenanceController extends Controller
{
    public function __construct(protected PlatformTenantService $service) {}

    public function purgeDemo()
    {
        $result = $this->service->purgeDemo(auth('api')->user());

        return ApiResponse::success($result, 'Tenant demo berhasil dihapus.');
    }

    public function seedDemo()
    {
        $result = $this->service->seedDemo(auth('api')->user());
        $message = $result['already_existed']
            ? 'Tenant demo sudah ada.'
            : 'Tenant demo berhasil dibuat.';

        return ApiResponse::success($result, $message);
    }
}