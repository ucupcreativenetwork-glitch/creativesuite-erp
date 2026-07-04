<?php

namespace App\Modules\Integration\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Modules\Business\Resources\AttendanceRecordResource;
use App\Modules\Integration\Models\IntegrationApiKey;
use App\Modules\Integration\Requests\ExternalBulkAttendanceRequest;
use App\Modules\Integration\Requests\ExternalClockAttendanceRequest;
use App\Modules\Integration\Services\AttendanceImportService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceImportService $service) {}

    public function import(ExternalBulkAttendanceRequest $request)
    {
        /** @var IntegrationApiKey $apiKey */
        $apiKey = $request->attributes->get('integration_api_key');

        $result = $this->service->importBulk(
            $apiKey->tenant_id,
            $apiKey->company_id,
            $request->validated('records'),
            $request->validated('source') ?? 'api',
        );

        return ApiResponse::success($result, 'Attendance import selesai.');
    }

    public function clockIn(ExternalClockAttendanceRequest $request)
    {
        /** @var IntegrationApiKey $apiKey */
        $apiKey = $request->attributes->get('integration_api_key');

        $record = $this->service->clockByEmployee(
            $apiKey->tenant_id,
            $apiKey->company_id,
            'in',
            $request->validated(),
        );

        return ApiResponse::success(new AttendanceRecordResource($record), 'Clock in berhasil.');
    }

    public function clockOut(ExternalClockAttendanceRequest $request)
    {
        /** @var IntegrationApiKey $apiKey */
        $apiKey = $request->attributes->get('integration_api_key');

        $record = $this->service->clockByEmployee(
            $apiKey->tenant_id,
            $apiKey->company_id,
            'out',
            $request->validated(),
        );

        return ApiResponse::success(new AttendanceRecordResource($record), 'Clock out berhasil.');
    }

    public function index(Request $request)
    {
        /** @var IntegrationApiKey $apiKey */
        $apiKey = $request->attributes->get('integration_api_key');

        $query = \App\Modules\Business\Models\AttendanceRecord::query()
            ->with('employee')
            ->where('tenant_id', $apiKey->tenant_id)
            ->where('company_id', $apiKey->company_id)
            ->orderByDesc('attendance_date');

        if ($request->filled('from_date')) {
            $query->where('attendance_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('attendance_date', '<=', $request->input('to_date'));
        }

        return ApiResponse::success(
            AttendanceRecordResource::collection($query->paginate($request->integer('per_page', 25))),
        );
    }
}