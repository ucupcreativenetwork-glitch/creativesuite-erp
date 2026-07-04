<?php

namespace App\Modules\Finance\Controllers\Api\V1;

use App\Modules\Finance\Resources\FiscalPeriodResource;
use App\Modules\Finance\Services\FiscalPeriodService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class FiscalPeriodController extends Controller
{
    public function __construct(protected FiscalPeriodService $fiscalPeriodService) {}

    public function index(): JsonResponse
    {
        $periods = $this->fiscalPeriodService->listForUser(
            auth('api')->user(),
            request()->integer('year') ?: null,
        );

        return ApiResponse::success(FiscalPeriodResource::collection($periods));
    }

    public function close(int $year, int $month): JsonResponse
    {
        $period = $this->fiscalPeriodService->close(auth('api')->user(), $year, $month);

        return ApiResponse::success(new FiscalPeriodResource($period));
    }

    public function lock(int $year, int $month): JsonResponse
    {
        $period = $this->fiscalPeriodService->lock(auth('api')->user(), $year, $month);

        return ApiResponse::success(new FiscalPeriodResource($period));
    }

    public function reopen(int $year, int $month): JsonResponse
    {
        $period = $this->fiscalPeriodService->reopen(auth('api')->user(), $year, $month);

        return ApiResponse::success(new FiscalPeriodResource($period));
    }
}