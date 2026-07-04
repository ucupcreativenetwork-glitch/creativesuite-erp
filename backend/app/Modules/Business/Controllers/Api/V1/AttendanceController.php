<?php

namespace App\Modules\Business\Controllers\Api\V1;

use App\Modules\Business\Requests\AdjustAttendanceRequest;
use App\Modules\Business\Requests\ClockAttendanceRequest;
use App\Modules\Business\Requests\CreateManualAttendanceRequest;
use App\Modules\Business\Resources\AttendanceRecordResource;
use App\Modules\Business\Services\AttendanceExportService;
use App\Modules\Business\Services\AttendanceLiveService;
use App\Modules\Business\Services\AttendanceService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $service,
        protected AttendanceLiveService $liveService,
        protected AttendanceExportService $exportService,
    ) {}

    public function today(): JsonResponse
    {
        $record = $this->service->today(auth('api')->user());

        return ApiResponse::success($record ? new AttendanceRecordResource($record) : null);
    }

    public function settings(): JsonResponse
    {
        return ApiResponse::success(
            app(\App\Modules\Business\Services\AttendanceCaptureValidator::class)
                ->settings(auth('api')->user()),
        );
    }

    public function clockIn(ClockAttendanceRequest $request): JsonResponse
    {
        $record = $this->service->clockIn(
            auth('api')->user(),
            $request->validated(),
            $request->file('photo'),
            $request->isMobileClient(),
        );

        return ApiResponse::success(new AttendanceRecordResource($record), 'Absen masuk berhasil.');
    }

    public function clockOut(ClockAttendanceRequest $request): JsonResponse
    {
        $record = $this->service->clockOut(
            auth('api')->user(),
            $request->validated(),
            $request->file('photo'),
            $request->isMobileClient(),
        );

        return ApiResponse::success(new AttendanceRecordResource($record), 'Absen pulang berhasil.');
    }

    public function live(): JsonResponse
    {
        return ApiResponse::success($this->liveService->dashboard(auth('api')->user()));
    }

    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'status' => ['nullable', 'string'],
            'employee_public_id' => ['nullable', 'uuid'],
        ]);

        return ApiResponse::success(
            $this->exportService->exportCsv(auth('api')->user(), $request->only([
                'from_date', 'to_date', 'status', 'employee_public_id',
            ])),
        );
    }

    public function index(Request $request): JsonResponse
    {
        $records = $this->service->list(auth('api')->user(), $request->only([
            'from_date', 'to_date', 'status', 'employee_public_id', 'per_page',
        ]));

        return ApiResponse::success(AttendanceRecordResource::collection($records));
    }

    public function adjust(AdjustAttendanceRequest $request, string $publicId): JsonResponse
    {
        $record = $this->service->adjust(
            auth('api')->user(),
            $publicId,
            $request->validated(),
        );

        return ApiResponse::success(new AttendanceRecordResource($record), 'Absensi berhasil dikoreksi.');
    }

    public function createManual(CreateManualAttendanceRequest $request): JsonResponse
    {
        $record = $this->service->createManual(
            auth('api')->user(),
            $request->validated(),
        );

        return ApiResponse::success(new AttendanceRecordResource($record), 'Absensi manual berhasil dicatat.');
    }

    public function monthlyReport(Request $request): JsonResponse
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $report = $this->service->monthlyReport(
            auth('api')->user(),
            (int) $request->input('year'),
            (int) $request->input('month'),
        );

        return ApiResponse::success($report);
    }
}