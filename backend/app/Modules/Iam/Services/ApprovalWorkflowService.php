<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Iam\Models\ApprovalWorkflowConfig;
use App\Modules\Iam\Models\ApprovalWorkflowStep;
use App\Modules\Iam\Models\UserCreationRequest;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Collection;

class ApprovalWorkflowService
{
    public function getDefaultWorkflow(int $companyId): ApprovalWorkflowConfig
    {
        return ApprovalWorkflowConfig::query()
            ->where('company_id', $companyId)
            ->where('module', 'USER_CREATION')
            ->where('is_active', true)
            ->where('is_default', true)
            ->with('steps')
            ->firstOr(function () {
                throw new ApiException('Workflow approval belum dikonfigurasi.', 422, 'WORKFLOW_NOT_CONFIGURED');
            });
    }

    public function getCurrentStep(UserCreationRequest $request): ?ApprovalWorkflowStep
    {
        if ($request->current_approval_level < 1) {
            return null;
        }

        return ApprovalWorkflowStep::query()
            ->where('workflow_config_id', $request->workflow_config_id)
            ->where('step_order', $request->current_approval_level)
            ->first();
    }

    public function assertCurrentApprover(UserCreationRequest $request, User $actor): ApprovalWorkflowStep
    {
        $step = $this->getCurrentStep($request);

        if (! $step) {
            throw new ApiException('Tidak ada level approval aktif.', 422, 'NO_ACTIVE_APPROVAL_STEP');
        }

        $hasRole = $actor->roles()->where('code', $step->approver_role_code)->exists()
            || ($step->approver_role_code === 'COMPANY_OWNER' && $actor->roles()->where('code', 'TENANT_OWNER')->exists());

        if (! $hasRole && ! $actor->hasPermission('iam.request.override')) {
            throw new ApiException('Anda bukan approver untuk level ini.', 403, 'NOT_CURRENT_APPROVER');
        }

        return $step;
    }

    public function getApproversForStep(ApprovalWorkflowStep $step, int $tenantId): Collection
    {
        $codes = [$step->approver_role_code];
        if ($step->approver_role_code === 'COMPANY_OWNER') {
            $codes[] = 'TENANT_OWNER';
        }

        $roleIds = Role::query()->where('tenant_id', $tenantId)->whereIn('code', $codes)->pluck('id');

        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('cs_core_roles.id', $roleIds))
            ->get();
    }

    public function isFinalStep(UserCreationRequest $request): bool
    {
        $maxStep = ApprovalWorkflowStep::query()
            ->where('workflow_config_id', $request->workflow_config_id)
            ->max('step_order');

        return $request->current_approval_level >= (int) $maxStep;
    }

    public function advanceLevel(UserCreationRequest $request): void
    {
        $request->update([
            'current_approval_level' => $request->current_approval_level + 1,
            'status' => \App\Modules\Iam\Enums\UserRequestStatus::Pending,
        ]);
    }
}