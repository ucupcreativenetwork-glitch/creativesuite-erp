<?php

namespace App\Modules\Iam\Controllers\Api\V1;

use App\Modules\Iam\Services\WorkflowManagementService;
use App\Support\Business\ChecksPermissions;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class WorkflowController extends Controller
{
    use ChecksPermissions;

    public function __construct(protected WorkflowManagementService $service) {}

    public function index(): JsonResponse
    {
        $actor = auth('api')->user();
        $this->assertPermission($actor, 'iam.workflow.read');

        $workflows = $this->service->list($actor->default_company_id)
            ->map(fn ($w) => $this->service->formatWorkflow($w));

        return ApiResponse::success($workflows);
    }

    public function seedAlternatives(): JsonResponse
    {
        $actor = auth('api')->user();
        $this->assertPermission($actor, 'iam.workflow.manage');

        $this->service->ensureAllWorkflows($actor->tenant_id, $actor->default_company_id, $actor->id);

        $workflows = $this->service->list($actor->default_company_id)
            ->map(fn ($w) => $this->service->formatWorkflow($w));

        return ApiResponse::success($workflows, 'Workflow B & C tersedia.');
    }

    public function setDefault(string $publicId): JsonResponse
    {
        $actor = auth('api')->user();
        $this->assertPermission($actor, 'iam.workflow.manage');

        $workflow = $this->service->setDefault($actor->default_company_id, $publicId);

        return ApiResponse::success($this->service->formatWorkflow($workflow), 'Workflow default diperbarui.');
    }
}