<?php

namespace App\Modules\Business\Controllers\Api\V1;

use App\Modules\Business\Requests\CreateQuotationRequest;
use App\Modules\Business\Requests\UpdateQuotationRequest;
use App\Modules\Business\Resources\QuotationResource;
use App\Modules\Business\Services\QuotationService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class QuotationController extends Controller
{
    public function __construct(protected QuotationService $service) {}

    public function index(): JsonResponse
    {
        $quotations = $this->service->list(auth('api')->user(), request()->only([
            'status', 'search', 'per_page',
        ]));

        return ApiResponse::success(QuotationResource::collection($quotations));
    }

    public function show(string $publicId): JsonResponse
    {
        $quotation = $this->service->show(auth('api')->user(), $publicId);

        return ApiResponse::success(new QuotationResource($quotation));
    }

    public function store(CreateQuotationRequest $request): JsonResponse
    {
        $quotation = $this->service->create(auth('api')->user(), $request->validated());

        return ApiResponse::success(new QuotationResource($quotation), 'Quotation created.', 201);
    }

    public function update(UpdateQuotationRequest $request, string $publicId): JsonResponse
    {
        $quotation = $this->service->update(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new QuotationResource($quotation), 'Quotation updated.');
    }

    public function destroy(string $publicId): JsonResponse
    {
        $this->service->delete(auth('api')->user(), $publicId);

        return ApiResponse::success(null, 'Quotation deleted.');
    }

    public function send(string $publicId): JsonResponse
    {
        $quotation = $this->service->send(auth('api')->user(), $publicId);

        return ApiResponse::success(new QuotationResource($quotation), 'Quotation sent.');
    }

    public function accept(string $publicId): JsonResponse
    {
        $quotation = $this->service->accept(auth('api')->user(), $publicId);

        return ApiResponse::success(new QuotationResource($quotation), 'Quotation accepted.');
    }
}