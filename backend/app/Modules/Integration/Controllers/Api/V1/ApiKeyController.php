<?php

namespace App\Modules\Integration\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Integration\Requests\StoreApiKeyRequest;
use App\Modules\Integration\Resources\IntegrationApiKeyResource;
use App\Modules\Integration\Services\ApiKeyService;
use App\Support\Http\ApiResponse;

class ApiKeyController extends Controller
{
    public function __construct(protected ApiKeyService $service) {}

    public function index()
    {
        return ApiResponse::success(
            IntegrationApiKeyResource::collection($this->service->list(auth('api')->user())),
        );
    }

    public function store(StoreApiKeyRequest $request)
    {
        $result = $this->service->create(auth('api')->user(), $request->validated());

        return ApiResponse::success([
            'api_key' => new IntegrationApiKeyResource($result['api_key']),
            'plain_text_key' => $result['plain_text_key'],
        ], 'API key created. Simpan key ini — tidak ditampilkan lagi.');
    }

    public function destroy(string $publicId)
    {
        $key = $this->service->revoke(auth('api')->user(), $publicId);

        return ApiResponse::success(new IntegrationApiKeyResource($key), 'API key dicabut.');
    }
}