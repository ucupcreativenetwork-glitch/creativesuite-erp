<?php

namespace App\Modules\Integration\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Integration\Requests\StoreWebhookRequest;
use App\Modules\Integration\Requests\UpdateWebhookRequest;
use App\Modules\Integration\Resources\WebhookEndpointResource;
use App\Modules\Integration\Services\WebhookService;
use App\Support\Http\ApiResponse;

class WebhookController extends Controller
{
    public function __construct(protected WebhookService $service) {}

    public function index()
    {
        return ApiResponse::success(
            WebhookEndpointResource::collection($this->service->list(auth('api')->user())),
        );
    }

    public function store(StoreWebhookRequest $request)
    {
        $endpoint = $this->service->create(auth('api')->user(), $request->validated());

        return ApiResponse::success(new WebhookEndpointResource($endpoint), 'Webhook endpoint dibuat.');
    }

    public function update(UpdateWebhookRequest $request, string $publicId)
    {
        $endpoint = $this->service->update(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new WebhookEndpointResource($endpoint), 'Webhook diperbarui.');
    }

    public function destroy(string $publicId)
    {
        $this->service->destroy(auth('api')->user(), $publicId);

        return ApiResponse::success(null, 'Webhook dihapus.');
    }

    public function meta()
    {
        return ApiResponse::success([
            'events' => config('integration.webhook_events', []),
            'scopes' => config('integration.available_scopes', []),
            'connector_types' => config('integration.connector_types', []),
            'connector_match_fields' => config('integration.connector_match_fields', []),
        ]);
    }
}