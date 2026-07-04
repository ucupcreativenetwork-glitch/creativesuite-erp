<?php

namespace App\Modules\Business\Controllers\Api\V1;

use App\Modules\Business\Requests\CreateLeaveRequest;
use App\Modules\Business\Resources\LeaveRequestResource;
use App\Modules\Business\Services\LeaveService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LeaveController extends Controller
{
    public function __construct(protected LeaveService $service) {}

    public function index(Request $request): JsonResponse
    {
        $records = $this->service->list(auth('api')->user(), $request->only(['status', 'per_page']));

        return ApiResponse::success(LeaveRequestResource::collection($records));
    }

    public function show(string $publicId): JsonResponse
    {
        $leave = $this->service->show(auth('api')->user(), $publicId);

        return ApiResponse::success(new LeaveRequestResource($leave));
    }

    public function store(CreateLeaveRequest $request): JsonResponse
    {
        $leave = $this->service->create(auth('api')->user(), $request->validated());

        return ApiResponse::success(new LeaveRequestResource($leave), 'Pengajuan cuti/izin berhasil diajukan.', 201);
    }

    public function approve(string $publicId): JsonResponse
    {
        $leave = $this->service->approve(auth('api')->user(), $publicId);

        return ApiResponse::success(new LeaveRequestResource($leave), 'Pengajuan disetujui.');
    }

    public function reject(Request $request, string $publicId): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $leave = $this->service->reject(auth('api')->user(), $publicId, $request->input('reason'));

        return ApiResponse::success(new LeaveRequestResource($leave), 'Pengajuan ditolak.');
    }

    public function cancel(string $publicId): JsonResponse
    {
        $leave = $this->service->cancel(auth('api')->user(), $publicId);

        return ApiResponse::success(new LeaveRequestResource($leave), 'Pengajuan dibatalkan.');
    }

    public function previewDays(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'employee_public_id' => ['sometimes', 'string', 'uuid'],
        ]);

        $preview = $this->service->previewDays(
            auth('api')->user(),
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('employee_public_id'),
        );

        return ApiResponse::success($preview);
    }
}
