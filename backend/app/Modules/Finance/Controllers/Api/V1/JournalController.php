<?php

namespace App\Modules\Finance\Controllers\Api\V1;

use App\Modules\Finance\Requests\CreateJournalRequest;
use App\Modules\Finance\Requests\VoidJournalRequest;
use App\Modules\Finance\Resources\JournalEntryResource;
use App\Modules\Finance\Services\JournalService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class JournalController extends Controller
{
    public function __construct(protected JournalService $journalService) {}

    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        $entries = $this->journalService->list($user, request()->only([
            'status', 'journal_type', 'from_date', 'to_date', 'per_page',
        ]));

        return ApiResponse::success(JournalEntryResource::collection($entries));
    }

    public function show(string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $entry = $this->journalService->show($user, $publicId);

        return ApiResponse::success(new JournalEntryResource($entry));
    }

    public function store(CreateJournalRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $entry = $this->journalService->createManual($user, $request->validated());

        return ApiResponse::success(new JournalEntryResource($entry), 'Journal created.', 201);
    }

    public function post(string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $entry = $this->journalService->post($user, $publicId);

        return ApiResponse::success(new JournalEntryResource($entry), 'Journal posted.');
    }

    public function void(VoidJournalRequest $request, string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->journalService->void($user, $publicId, $request->validated('reason'));

        return ApiResponse::success([
            'voided' => new JournalEntryResource($result['voided']),
            'reversal' => new JournalEntryResource($result['reversal']),
        ], 'Journal voided and reversal posted.');
    }
}