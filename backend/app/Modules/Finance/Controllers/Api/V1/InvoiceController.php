<?php

namespace App\Modules\Finance\Controllers\Api\V1;

use App\Modules\Finance\Requests\CreateInvoiceRequest;
use App\Modules\Finance\Requests\UpdateInvoiceRequest;
use App\Modules\Finance\Resources\InvoiceResource;
use App\Modules\Finance\Services\InvoiceService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}

    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        $invoices = $this->invoiceService->list($user, request()->only([
            'invoice_type', 'status', 'per_page',
        ]));

        return ApiResponse::success(InvoiceResource::collection($invoices));
    }

    public function show(string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $invoice = $this->invoiceService->show($user, $publicId);

        return ApiResponse::success(new InvoiceResource($invoice));
    }

    public function store(CreateInvoiceRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $invoice = $this->invoiceService->create($user, $request->validated());

        return ApiResponse::success(new InvoiceResource($invoice), 'Invoice created.', 201);
    }

    public function update(UpdateInvoiceRequest $request, string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $invoice = $this->invoiceService->update($user, $publicId, $request->validated());

        return ApiResponse::success(new InvoiceResource($invoice), 'Invoice updated.');
    }

    public function post(string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $invoice = $this->invoiceService->post($user, $publicId);

        return ApiResponse::success(new InvoiceResource($invoice), 'Invoice posted with auto-journal.');
    }
}