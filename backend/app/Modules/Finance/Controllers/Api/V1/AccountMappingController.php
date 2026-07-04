<?php

namespace App\Modules\Finance\Controllers\Api\V1;

use App\Modules\Finance\Enums\AccountMappingKey;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Finance\Services\AccountMappingService;
use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


class AccountMappingController extends Controller
{
    public function __construct(protected AccountMappingService $accountMappingService) {}

    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        $this->assertPermission($user, 'fin.coa.read');

        $mappings = $this->accountMappingService->listForCompany($user->default_company_id);

        return ApiResponse::success($mappings);
    }

    public function update(Request $request, string $mappingKey): JsonResponse
    {
        $user = auth('api')->user();
        $this->assertPermission($user, 'fin.coa.update');

        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:cs_fin_chart_of_accounts,id'],
        ]);

        if (! AccountMappingKey::tryFrom($mappingKey)) {
            throw new ApiException('Invalid mapping key.', 422, 'INVALID_MAPPING_KEY');
        }

        $mapping = AccountMapping::query()
            ->where('company_id', $user->default_company_id)
            ->where('mapping_key', $mappingKey)
            ->firstOrFail();

        $mapping->update(['account_id' => $data['account_id']]);

        return ApiResponse::success([
            'mapping_key' => $mapping->mapping_key,
            'account_id' => $mapping->account_id,
            'account_code' => $mapping->fresh('account')->account?->code,
            'account_name' => $mapping->account?->name,
        ], 'Account mapping updated.');
    }

    protected function assertPermission($user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }
}