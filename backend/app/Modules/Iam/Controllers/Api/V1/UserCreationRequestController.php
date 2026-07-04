<?php

namespace App\Modules\Iam\Controllers\Api\V1;

use App\Modules\Iam\Requests\RejectUserCreationRequest;
use App\Modules\Iam\Requests\RevisionUserCreationRequest;
use App\Modules\Iam\Requests\StoreUserCreationRequest;
use App\Modules\Iam\Requests\UpdateUserCreationRequest;
use App\Modules\Iam\Resources\UserCreationRequestResource;
use App\Modules\Iam\Services\UserRequestService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserCreationRequestController extends Controller
{
    public function __construct(protected UserRequestService $service) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->service->list(auth('api')->user(), $request->all());

        return ApiResponse::success(UserCreationRequestResource::collection($items));
    }

    public function store(StoreUserCreationRequest $request): JsonResponse
    {
        $item = $this->service->create(auth('api')->user(), $request->validated());

        return ApiResponse::success(new UserCreationRequestResource($item), 'Permintaan user dibuat.', 201);
    }

    public function show(string $publicId): JsonResponse
    {
        $item = $this->service->show(auth('api')->user(), $publicId);

        return ApiResponse::success(new UserCreationRequestResource($item));
    }

    public function update(UpdateUserCreationRequest $request, string $publicId): JsonResponse
    {
        $item = $this->service->update(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new UserCreationRequestResource($item), 'Permintaan diperbarui.');
    }

    public function submit(string $publicId): JsonResponse
    {
        $item = $this->service->submit(auth('api')->user(), $publicId);

        return ApiResponse::success(new UserCreationRequestResource($item), 'Permintaan diajukan.');
    }

    public function cancel(string $publicId): JsonResponse
    {
        $item = $this->service->cancel(auth('api')->user(), $publicId);

        return ApiResponse::success(new UserCreationRequestResource($item), 'Permintaan dibatalkan.');
    }

    public function approve(Request $request, string $publicId): JsonResponse
    {
        $item = $this->service->approve(auth('api')->user(), $publicId, $request->input('notes'));

        return ApiResponse::success(new UserCreationRequestResource($item), 'Permintaan disetujui.');
    }

    public function reject(RejectUserCreationRequest $request, string $publicId): JsonResponse
    {
        $item = $this->service->reject(auth('api')->user(), $publicId, $request->validated('rejection_reason'));

        return ApiResponse::success(new UserCreationRequestResource($item), 'Permintaan ditolak.');
    }

    public function requestRevision(RevisionUserCreationRequest $request, string $publicId): JsonResponse
    {
        $item = $this->service->requestRevision(auth('api')->user(), $publicId, $request->validated('revision_notes'));

        return ApiResponse::success(new UserCreationRequestResource($item), 'Revisi diminta.');
    }

    public function overrideApprove(string $publicId): JsonResponse
    {
        $item = $this->service->overrideApprove(auth('api')->user(), $publicId);

        return ApiResponse::success(new UserCreationRequestResource($item), 'Permintaan disetujui (override).');
    }
}