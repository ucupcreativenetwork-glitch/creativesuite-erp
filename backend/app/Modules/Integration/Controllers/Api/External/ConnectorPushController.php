<?php

namespace App\Modules\Integration\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Modules\Integration\Services\ConnectorService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;

class ConnectorPushController extends Controller
{
    public function __construct(protected ConnectorService $service) {}

    public function push(Request $request)
    {
        $token = $request->header('X-Connector-Token') ?? $request->input('ingest_token');
        if (! $token) {
            return ApiResponse::error('Connector token required.', 401, 'CONNECTOR_TOKEN_REQUIRED');
        }

        $result = $this->service->ingestPush($token, $request->all());

        return ApiResponse::success($result, 'Connector data diproses.');
    }
}