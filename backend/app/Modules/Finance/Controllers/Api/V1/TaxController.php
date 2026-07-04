<?php

namespace App\Modules\Finance\Controllers\Api\V1;

use App\Modules\Finance\Requests\ApproveEfakturRequest;
use App\Modules\Finance\Resources\EbupotDocumentResource;
use App\Modules\Finance\Resources\EfakturDocumentResource;
use App\Modules\Finance\Resources\Pph23TransactionResource;
use App\Modules\Finance\Resources\PpnTransactionResource;
use App\Modules\Finance\Resources\SptMasaPpnResource;
use App\Modules\Finance\Services\Pph23Service;
use App\Modules\Finance\Services\PpnService;
use App\Modules\Finance\Services\SptMasaPpnService;
use App\Modules\Finance\Services\TaxCalculatorService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TaxController extends Controller
{
    public function __construct(
        protected TaxCalculatorService $taxCalculator,
        protected PpnService $ppnService,
        protected SptMasaPpnService $sptService,
        protected Pph23Service $pph23Service,
    ) {}

    public function calculatePpn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_ppn_inclusive' => ['nullable', 'boolean'],
        ]);

        $result = $this->taxCalculator->calculatePpn(
            (float) $data['amount'],
            (float) ($data['ppn_rate'] ?? 12),
            (bool) ($data['is_ppn_inclusive'] ?? false),
        );

        return ApiResponse::success($result);
    }

    public function ppnTransactions(): JsonResponse
    {
        $user = auth('api')->user();
        $txns = $this->ppnService->listTransactions($user, request()->only([
            'transaction_type', 'year', 'month', 'per_page',
        ]));

        return ApiResponse::success(PpnTransactionResource::collection($txns));
    }

    public function efakturList(): JsonResponse
    {
        $user = auth('api')->user();
        $docs = $this->ppnService->listEfaktur($user, request()->only(['status', 'per_page']));

        return ApiResponse::success(EfakturDocumentResource::collection($docs));
    }

    public function requestEfaktur(int $ppnTransactionId): JsonResponse
    {
        $user = auth('api')->user();
        $doc = $this->ppnService->requestEfaktur($user, $ppnTransactionId);

        return ApiResponse::success(new EfakturDocumentResource($doc), 'e-Faktur requested.', 201);
    }

    public function approveEfaktur(ApproveEfakturRequest $request, string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $doc = $this->ppnService->approveEfaktur($user, $publicId, $request->validated());

        return ApiResponse::success(new EfakturDocumentResource($doc), 'e-Faktur approved.');
    }

    public function sptList(): JsonResponse
    {
        $user = auth('api')->user();
        $list = $this->sptService->list($user, request()->integer('year') ?: null);

        return ApiResponse::success(SptMasaPpnResource::collection($list));
    }

    public function sptShow(int $year, int $month): JsonResponse
    {
        $user = auth('api')->user();

        try {
            $spt = $this->sptService->show($user, $year, $month);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $spt = $this->sptService->generate($user, $year, $month);
        }

        return ApiResponse::success(new SptMasaPpnResource($spt));
    }

    public function sptGenerate(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $spt = $this->sptService->generate($user, $data['year'], $data['month']);

        return ApiResponse::success(new SptMasaPpnResource($spt), 'SPT Masa PPN generated.');
    }

    public function sptFinalize(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $spt = $this->sptService->finalize($user, $data['year'], $data['month']);

        return ApiResponse::success(new SptMasaPpnResource($spt), 'SPT Masa PPN finalized.');
    }

    public function pph23Transactions(): JsonResponse
    {
        $user = auth('api')->user();
        $txns = $this->pph23Service->listTransactions($user, request()->only([
            'year', 'month', 'per_page',
        ]));

        return ApiResponse::success(Pph23TransactionResource::collection($txns));
    }

    public function ebupotList(): JsonResponse
    {
        $user = auth('api')->user();
        $docs = $this->pph23Service->listEbupot($user, request()->only(['status', 'per_page']));

        return ApiResponse::success(EbupotDocumentResource::collection($docs));
    }

    public function issueEbupot(Request $request, int $pph23TransactionId): JsonResponse
    {
        $user = auth('api')->user();
        $data = $request->validate([
            'nomor_bupot' => ['nullable', 'string', 'max:30'],
            'djp_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $doc = $this->pph23Service->issueEbupot($user, $pph23TransactionId, $data);

        return ApiResponse::success(new EbupotDocumentResource($doc), 'e-Bupot issued.', 201);
    }
}