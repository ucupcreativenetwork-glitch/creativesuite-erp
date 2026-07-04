<?php

namespace App\Modules\Iam\Controllers\Api\V1;

use App\Modules\Core\Models\Company;
use App\Modules\Iam\Services\IamBootstrapService;
use App\Support\Business\ChecksPermissions;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class IamBootstrapController extends Controller
{
    use ChecksPermissions;

    public function bootstrap(IamBootstrapService $bootstrap): JsonResponse
    {
        $actor = auth('api')->user();
        $this->assertPermission($actor, 'iam.workflow.manage');

        $company = Company::query()->findOrFail($actor->default_company_id);
        $tenant = \App\Modules\Core\Models\Tenant::query()->findOrFail($actor->tenant_id);
        $bootstrap->bootstrapTenant($tenant, $company, $actor->id);

        return ApiResponse::success(null, 'IAM bootstrap selesai — departemen, role, dan workflow telah dibuat.');
    }
}