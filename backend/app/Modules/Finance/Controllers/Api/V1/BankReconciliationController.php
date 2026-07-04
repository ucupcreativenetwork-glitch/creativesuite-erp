<?php

namespace App\Modules\Finance\Controllers\Api\V1;

use App\Modules\Finance\Resources\BankStatementLineResource;
use App\Modules\Finance\Resources\PaymentResource;
use App\Modules\Finance\Services\BankReconciliationService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BankReconciliationController extends Controller
{
    public function __construct(protected BankReconciliationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $lines = $this->service->list(auth('api')->user(), $request->only([
            'status', 'bank_account_id', 'per_page',
        ]));

        return ApiResponse::success(BankStatementLineResource::collection($lines));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:cs_fin_chart_of_accounts,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'debit' => ['nullable', 'numeric', 'min:0'],
            'credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $line = $this->service->create(auth('api')->user(), $data);

        return ApiResponse::success(new BankStatementLineResource($line), 'Bank statement line created.', 201);
    }

    public function unmatchedPayments(): JsonResponse
    {
        $payments = $this->service->unmatchedPayments(auth('api')->user());

        return ApiResponse::success(PaymentResource::collection($payments));
    }

    public function match(string $linePublicId, string $paymentPublicId): JsonResponse
    {
        $line = $this->service->match(auth('api')->user(), $linePublicId, $paymentPublicId);

        return ApiResponse::success(new BankStatementLineResource($line->load(['bankAccount', 'matchedPayment'])), 'Matched.');
    }
}