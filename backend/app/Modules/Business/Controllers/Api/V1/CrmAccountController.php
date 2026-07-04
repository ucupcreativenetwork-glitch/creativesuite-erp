<?php

namespace App\Modules\Business\Controllers\Api\V1;

use App\Modules\Business\Requests\CreateCrmAccountRequest;
use App\Modules\Business\Requests\CreateCrmContactRequest;
use App\Modules\Business\Requests\ListCrmAccountsRequest;
use App\Modules\Business\Requests\UpdateCrmAccountRequest;
use App\Modules\Business\Resources\CrmAccountResource;
use App\Modules\Business\Resources\CrmContactResource;
use App\Modules\Business\Services\CrmAccountService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CrmAccountController extends Controller
{
    public function __construct(protected CrmAccountService $service) {}

    public function index(ListCrmAccountsRequest $request): JsonResponse
    {
        $accounts = $this->service->list(auth('api')->user(), $request->validated());

        return ApiResponse::success(CrmAccountResource::collection($accounts));
    }

    public function show(string $publicId): JsonResponse
    {
        $account = $this->service->show(auth('api')->user(), $publicId);

        return ApiResponse::success(new CrmAccountResource($account));
    }

    public function store(CreateCrmAccountRequest $request): JsonResponse
    {
        $account = $this->service->create(auth('api')->user(), $request->validated());

        return ApiResponse::success(new CrmAccountResource($account), 'Account created.', 201);
    }

    public function update(UpdateCrmAccountRequest $request, string $publicId): JsonResponse
    {
        $account = $this->service->update(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new CrmAccountResource($account), 'Account updated.');
    }

    public function destroy(string $publicId): JsonResponse
    {
        $this->service->delete(auth('api')->user(), $publicId);

        return ApiResponse::success(null, 'Account deleted.');
    }

    public function contacts(string $publicId): JsonResponse
    {
        $contacts = $this->service->listContacts(auth('api')->user(), $publicId);

        return ApiResponse::success(CrmContactResource::collection($contacts));
    }

    public function storeContact(CreateCrmContactRequest $request, string $publicId): JsonResponse
    {
        $contact = $this->service->createContact(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new CrmContactResource($contact), 'Contact created.', 201);
    }
}
