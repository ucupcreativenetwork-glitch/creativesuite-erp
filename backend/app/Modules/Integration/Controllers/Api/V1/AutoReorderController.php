<?php

namespace App\Modules\Integration\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Integration\Requests\StoreAutoReorderRuleRequest;
use App\Modules\Integration\Requests\UpdateAutoReorderRuleRequest;
use App\Modules\Integration\Resources\AutoReorderRuleResource;
use App\Modules\Integration\Services\AutoReorderService;
use App\Support\Http\ApiResponse;

class AutoReorderController extends Controller
{
    public function __construct(protected AutoReorderService $service) {}

    public function index()
    {
        return ApiResponse::success(
            AutoReorderRuleResource::collection($this->service->list(auth('api')->user())),
        );
    }

    public function store(StoreAutoReorderRuleRequest $request)
    {
        $rule = $this->service->create(auth('api')->user(), $request->validated());

        return ApiResponse::success(new AutoReorderRuleResource($rule), 'Aturan auto-reorder dibuat.');
    }

    public function update(UpdateAutoReorderRuleRequest $request, string $publicId)
    {
        $rule = $this->service->update(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new AutoReorderRuleResource($rule), 'Aturan diperbarui.');
    }

    public function destroy(string $publicId)
    {
        $this->service->destroy(auth('api')->user(), $publicId);

        return ApiResponse::success(null, 'Aturan dihapus.');
    }

    public function run()
    {
        $results = $this->service->runForTenant(auth('api')->user());

        return ApiResponse::success(['results' => $results], 'Auto-reorder selesai dijalankan.');
    }
}