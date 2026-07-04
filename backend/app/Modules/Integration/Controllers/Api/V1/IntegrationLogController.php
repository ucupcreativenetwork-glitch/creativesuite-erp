<?php

namespace App\Modules\Integration\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Integration\Resources\ConnectorIngestLogResource;
use App\Modules\Integration\Resources\WebhookDeliveryResource;
use App\Modules\Integration\Services\IntegrationLogService;
use App\Modules\Integration\Services\WebhookService;
use App\Support\Http\ApiResponse;

class IntegrationLogController extends Controller
{
    public function __construct(
        protected IntegrationLogService $logService,
        protected WebhookService $webhookService,
    ) {}

    public function connectorLogs(string $publicId)
    {
        $logs = $this->logService->listConnectorLogs(
            auth('api')->user(),
            $publicId,
            request()->only(['per_page']),
        );

        return ApiResponse::success(ConnectorIngestLogResource::collection($logs));
    }

    public function webhookDeliveries(string $publicId)
    {
        $deliveries = $this->webhookService->listDeliveries(
            auth('api')->user(),
            $publicId,
            request()->only(['per_page']),
        );

        return ApiResponse::success(WebhookDeliveryResource::collection($deliveries));
    }
}