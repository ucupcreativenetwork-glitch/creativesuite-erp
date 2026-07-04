<?php

namespace App\Modules\Integration\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Integration\Requests\StoreConnectorRequest;
use App\Modules\Integration\Requests\UpdateConnectorRequest;
use App\Modules\Integration\Resources\ConnectorConfigResource;
use App\Modules\Integration\Services\ConnectorService;
use App\Support\Http\ApiResponse;

class ConnectorController extends Controller
{
    public function __construct(protected ConnectorService $service) {}

    public function index()
    {
        return ApiResponse::success(
            ConnectorConfigResource::collection($this->service->list(auth('api')->user())),
        );
    }

    public function store(StoreConnectorRequest $request)
    {
        $result = $this->service->create(auth('api')->user(), $request->validated());

        return ApiResponse::success([
            'connector' => new ConnectorConfigResource($result['connector']),
            'ingest_token' => $result['ingest_token'],
            'push_url' => $result['push_url'],
        ], 'Connector dibuat. Simpan ingest token — tidak ditampilkan lagi.');
    }

    public function update(UpdateConnectorRequest $request, string $publicId)
    {
        $connector = $this->service->update(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new ConnectorConfigResource($connector), 'Connector diperbarui.');
    }

    public function destroy(string $publicId)
    {
        $this->service->destroy(auth('api')->user(), $publicId);

        return ApiResponse::success(null, 'Connector dihapus.');
    }
}