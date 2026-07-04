<?php

namespace App\Modules\Business\Controllers\Api\V1;

use App\Modules\Business\Requests\UpdateHrSettingsRequest;
use App\Modules\Business\Services\HrMeService;
use App\Modules\Business\Services\HrSettingsService;
use App\Modules\Business\Services\HrSummaryService;
use App\Modules\Business\Services\LeaveService;
use App\Modules\Business\Services\PayrollService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class HrController extends Controller
{
    public function __construct(
        protected HrSummaryService $summaryService,
        protected PayrollService $payrollService,
        protected LeaveService $leaveService,
        protected HrMeService $hrMeService,
        protected HrSettingsService $hrSettingsService,
    ) {}

    public function summary(): JsonResponse
    {
        $data = $this->summaryService->summary(auth('api')->user());

        return ApiResponse::success($data);
    }

    public function myPayslips(): JsonResponse
    {
        $slips = $this->payrollService->myPayslips(auth('api')->user());

        return ApiResponse::success($slips);
    }

    public function myPayslip(string $runPublicId): JsonResponse
    {
        $slip = $this->payrollService->myPayslip(auth('api')->user(), $runPublicId);

        return ApiResponse::success($slip);
    }

    public function myLeaveBalance(): JsonResponse
    {
        $balance = $this->leaveService->myLeaveBalance(auth('api')->user());

        return ApiResponse::success($balance);
    }

    public function me(): JsonResponse
    {
        $profile = $this->hrMeService->profile(auth('api')->user());

        return ApiResponse::success($profile);
    }

    public function settings(): JsonResponse
    {
        return ApiResponse::success($this->hrSettingsService->get(auth('api')->user()));
    }

    public function updateSettings(UpdateHrSettingsRequest $request): JsonResponse
    {
        $settings = $this->hrSettingsService->update(
            auth('api')->user(),
            $request->validated(),
        );

        return ApiResponse::success($settings, 'Pengaturan HR disimpan.');
    }
}
