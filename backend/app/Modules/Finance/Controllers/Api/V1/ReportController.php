<?php

namespace App\Modules\Finance\Controllers\Api\V1;

use App\Modules\Finance\Services\ReportService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function generalLedger(): JsonResponse
    {
        $user = auth('api')->user();
        $data = $this->reportService->generalLedger($user, request()->only([
            'account_id', 'from_date', 'to_date',
        ]));

        return ApiResponse::success($data);
    }

    public function trialBalance(): JsonResponse
    {
        $user = auth('api')->user();
        $data = $this->reportService->trialBalance($user, request()->only([
            'from_date', 'to_date',
        ]));

        return ApiResponse::success($data);
    }

    public function profitLoss(): JsonResponse
    {
        $user = auth('api')->user();
        $data = $this->reportService->profitLoss($user, request()->only([
            'from_date', 'to_date',
        ]));

        return ApiResponse::success($data);
    }

    public function arAging(): JsonResponse
    {
        $user = auth('api')->user();
        $data = $this->reportService->arAging($user, request()->only([
            'as_of_date',
        ]));

        return ApiResponse::success($data);
    }

    public function balanceSheet(): JsonResponse
    {
        $user = auth('api')->user();
        $data = $this->reportService->balanceSheet($user, request()->only([
            'as_of_date',
        ]));

        return ApiResponse::success($data);
    }

    public function apAging(): JsonResponse
    {
        $user = auth('api')->user();
        $data = $this->reportService->apAging($user, request()->only([
            'as_of_date',
        ]));

        return ApiResponse::success($data);
    }
}