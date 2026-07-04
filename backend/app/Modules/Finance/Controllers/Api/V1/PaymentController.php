<?php

namespace App\Modules\Finance\Controllers\Api\V1;

use App\Modules\Finance\Requests\CreatePaymentRequest;
use App\Modules\Finance\Resources\PaymentResource;
use App\Modules\Finance\Services\PaymentService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        $payments = $this->paymentService->list($user, request()->only([
            'payment_type', 'per_page',
        ]));

        return ApiResponse::success(PaymentResource::collection($payments));
    }

    public function show(string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $payment = $this->paymentService->show($user, $publicId);

        return ApiResponse::success(new PaymentResource($payment));
    }

    public function store(CreatePaymentRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $payment = $this->paymentService->create($user, $request->validated());

        return ApiResponse::success(new PaymentResource($payment), 'Payment created.', 201);
    }

    public function post(string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $payment = $this->paymentService->post($user, $publicId);

        return ApiResponse::success(new PaymentResource($payment), 'Payment posted with auto-journal.');
    }
}