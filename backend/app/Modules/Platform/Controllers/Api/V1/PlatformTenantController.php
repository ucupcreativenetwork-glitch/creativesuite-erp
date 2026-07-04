<?php

namespace App\Modules\Platform\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Requests\PurgePlatformTenantRequest;
use App\Modules\Platform\Requests\UpdatePlatformTenantRequest;
use App\Modules\Platform\Resources\PlatformTenantResource;
use App\Modules\Platform\Services\PlatformTenantService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;

class PlatformTenantController extends Controller
{
    public function __construct(protected PlatformTenantService $service) {}

    public function index(Request $request)
    {
        $tenants = $this->service->list($request->only(['status', 'search', 'per_page']));

        return ApiResponse::success(PlatformTenantResource::collection($tenants));
    }

    public function show(string $publicId)
    {
        return ApiResponse::success($this->service->show($publicId));
    }

    public function update(UpdatePlatformTenantRequest $request, string $publicId)
    {
        $tenant = $this->service->update(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new PlatformTenantResource($tenant), 'Tenant diperbarui.');
    }

    public function suspend(string $publicId)
    {
        $tenant = $this->service->suspend(auth('api')->user(), $publicId);

        return ApiResponse::success(new PlatformTenantResource($tenant), 'Tenant ditangguhkan.');
    }

    public function activate(string $publicId)
    {
        $tenant = $this->service->activate(auth('api')->user(), $publicId);

        return ApiResponse::success(new PlatformTenantResource($tenant), 'Tenant diaktifkan.');
    }

    public function destroy(PurgePlatformTenantRequest $request, string $publicId)
    {
        $result = $this->service->purge(
            auth('api')->user(),
            $publicId,
            $request->validated('confirmation'),
        );

        return ApiResponse::success($result, 'Tenant dan seluruh datanya dihapus.');
    }
}