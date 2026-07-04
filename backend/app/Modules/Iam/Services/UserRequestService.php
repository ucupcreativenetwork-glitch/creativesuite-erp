<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\User;
use App\Modules\Iam\Enums\ApprovalAction;
use App\Modules\Iam\Enums\UserRequestStatus;
use App\Modules\Iam\Models\ApprovalHistory;
use App\Modules\Iam\Models\Department;
use App\Modules\Iam\Models\UserCreationRequest;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserRequestService
{
    use ChecksPermissions;

    public function __construct(
        protected DepartmentRoleGuard $roleGuard,
        protected ApprovalWorkflowService $workflowService,
        protected UserProvisioningService $provisioningService,
        protected AuditLogService $auditLog,
        protected NotificationDispatcher $notifications,
    ) {}

    public function list(User $actor, array $filters = [])
    {
        $query = UserCreationRequest::query()
            ->with(['department', 'requestedRole', 'requester', 'branch', 'workflow.steps'])
            ->orderByDesc('created_at');

        if ($actor->hasPermission('iam.request.read.all')) {
            // all company requests
        } elseif ($actor->hasPermission('iam.request.approve')) {
            $query->whereIn('status', [
                UserRequestStatus::Pending,
                UserRequestStatus::InReview,
            ]);
        } elseif ($actor->hasPermission('iam.request.read.own')) {
            $query->where('requested_by', $actor->id);
        } else {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('full_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('request_number', 'like', "%{$s}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function show(User $actor, string $publicId): UserCreationRequest
    {
        $request = UserCreationRequest::query()
            ->where('public_id', $publicId)
            ->with(['department', 'requestedRole', 'requester', 'branch', 'directManager', 'history.actor', 'createdUser', 'workflow.steps'])
            ->firstOrFail();

        $this->assertCanView($actor, $request);

        return $request;
    }

    public function create(User $actor, array $data): UserCreationRequest
    {
        $this->assertPermission($actor, 'iam.request.create');

        $department = $this->resolveDepartment($actor, $data['department_public_id'] ?? null);

        $workflow = $this->workflowService->getDefaultWorkflow($actor->default_company_id);
        $role = $this->roleGuard->assertRoleAllowedForDepartment(
            $department->id,
            (int) $data['requested_role_id'],
            $actor->tenant_id,
        );

        if (UserCreationRequest::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('email', $data['email'])
            ->whereNotIn('status', [UserRequestStatus::Rejected, UserRequestStatus::Cancelled])
            ->exists()) {
            throw new ApiException('Sudah ada permintaan aktif untuk email ini.', 409, 'DUPLICATE_REQUEST');
        }

        return DB::transaction(function () use ($actor, $data, $department, $workflow, $role) {
            $request = UserCreationRequest::query()->create([
                'public_id' => (string) Str::uuid(),
                'request_number' => $this->generateRequestNumber(),
                'tenant_id' => $actor->tenant_id,
                'company_id' => $actor->default_company_id,
                'branch_id' => $data['branch_id'] ?? $actor->default_branch_id,
                'department_id' => $department->id,
                'requested_by' => $actor->id,
                'requested_role_id' => $role->id,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'position' => $data['position'] ?? null,
                'direct_manager_id' => $data['direct_manager_id'] ?? $actor->id,
                'notes' => $data['notes'] ?? null,
                'status' => UserRequestStatus::Draft,
                'current_approval_level' => 0,
                'workflow_config_id' => $workflow->id,
            ]);

            $this->auditLog->record($actor, 'USER_REQUEST_CREATED', 'UserCreationRequest', $request->id, $request->public_id, null, $request->only(['email', 'status']), $request->company_id);

            if (! empty($data['submit'])) {
                return $this->submit($actor, $request->public_id);
            }

            return $request->fresh(['department', 'requestedRole']);
        });
    }

    public function update(User $actor, string $publicId, array $data): UserCreationRequest
    {
        $this->assertPermission($actor, 'iam.request.update.own');
        $request = $this->findOwnEditable($actor, $publicId);

        if (isset($data['requested_role_id'])) {
            $this->roleGuard->assertRoleAllowedForDepartment(
                $request->department_id,
                (int) $data['requested_role_id'],
                $actor->tenant_id,
            );
        }

        $old = $request->only(['full_name', 'email', 'phone', 'position', 'notes']);
        $request->update(array_filter([
            'full_name' => $data['full_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'position' => $data['position'] ?? null,
            'direct_manager_id' => $data['direct_manager_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'requested_role_id' => $data['requested_role_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
        ], fn ($v) => $v !== null));

        $this->auditLog->record($actor, 'USER_REQUEST_UPDATED', 'UserCreationRequest', $request->id, $request->public_id, $old, $request->only(['full_name', 'email']), $request->company_id);

        if (! empty($data['submit'])) {
            return $this->submit($actor, $request->public_id);
        }

        return $request->fresh(['department', 'requestedRole']);
    }

    public function submit(User $actor, string $publicId): UserCreationRequest
    {
        $request = $this->findOwnEditable($actor, $publicId);

        $request->update([
            'status' => UserRequestStatus::Pending,
            'current_approval_level' => 1,
            'submitted_at' => now(),
        ]);

        $this->recordHistory($request, $actor, ApprovalAction::Submitted, 0, 'Request diajukan');
        $this->auditLog->record($actor, 'USER_REQUEST_SUBMITTED', 'UserCreationRequest', $request->id, $request->public_id, null, ['status' => 'PENDING'], $request->company_id);

        $step = $this->workflowService->getCurrentStep($request);
        if ($step) {
            $approvers = $this->workflowService->getApproversForStep($step, $actor->tenant_id);
            $this->notifications->notifyRequestPending($request->fresh(['requester']), $approvers);
        }

        return $request->fresh(['department', 'requestedRole', 'workflow.steps']);
    }

    public function cancel(User $actor, string $publicId): UserCreationRequest
    {
        $request = UserCreationRequest::query()->where('public_id', $publicId)->firstOrFail();

        if ($request->requested_by !== $actor->id) {
            $this->assertPermission($actor, 'iam.request.cancel.any');
        } else {
            $this->assertPermission($actor, 'iam.request.cancel.own');
        }

        if (! in_array($request->status, [UserRequestStatus::Draft, UserRequestStatus::Pending, UserRequestStatus::RevisionRequested], true)) {
            throw new ApiException('Request tidak dapat dibatalkan.', 422, 'CANNOT_CANCEL');
        }

        $request->update(['status' => UserRequestStatus::Cancelled, 'cancelled_at' => now()]);
        $this->recordHistory($request, $actor, ApprovalAction::Cancelled, $request->current_approval_level, 'Dibatalkan');
        $this->auditLog->record($actor, 'USER_REQUEST_CANCELLED', 'UserCreationRequest', $request->id, $request->public_id, null, ['status' => 'CANCELLED'], $request->company_id);

        return $request;
    }

    public function approve(User $actor, string $publicId, ?string $notes = null): UserCreationRequest
    {
        $this->assertPermission($actor, 'iam.request.approve');
        $request = UserCreationRequest::query()->where('public_id', $publicId)->firstOrFail();

        if (! in_array($request->status, [UserRequestStatus::Pending, UserRequestStatus::InReview], true)) {
            throw new ApiException('Request tidak dalam status approval.', 422, 'INVALID_STATUS');
        }

        $step = $this->workflowService->assertCurrentApprover($request, $actor);

        return DB::transaction(function () use ($actor, $request, $step, $notes) {
            $this->recordHistory($request, $actor, ApprovalAction::Approved, $request->current_approval_level, $notes);

            if ($this->workflowService->isFinalStep($request)) {
                $request->update([
                    'status' => UserRequestStatus::Approved,
                    'approved_by' => $actor->id,
                    'approved_at' => now(),
                ]);

                $this->provisioningService->createFromRequest($request, $actor);
                $this->notifications->notifyRequester($request->fresh(['requester']), 'USER_REQUEST_APPROVED',
                    'Permintaan user disetujui',
                    "Permintaan {$request->request_number} telah disetujui. User telah dibuat.");
                $this->auditLog->record($actor, 'USER_REQUEST_APPROVED', 'UserCreationRequest', $request->id, $request->public_id, null, ['status' => 'APPROVED'], $request->company_id);
            } else {
                $this->workflowService->advanceLevel($request);
                $nextStep = $this->workflowService->getCurrentStep($request->fresh());
                if ($nextStep) {
                    $approvers = $this->workflowService->getApproversForStep($nextStep, $actor->tenant_id);
                    $this->notifications->notifyRequestPending($request->fresh(['requester']), $approvers);
                }
                $this->auditLog->record($actor, 'USER_REQUEST_APPROVED', 'UserCreationRequest', $request->id, $request->public_id, null, ['level' => $request->current_approval_level], $request->company_id);
            }

            return $request->fresh(['department', 'requestedRole', 'createdUser']);
        });
    }

    public function reject(User $actor, string $publicId, string $reason): UserCreationRequest
    {
        $this->assertPermission($actor, 'iam.request.reject');
        $request = UserCreationRequest::query()->where('public_id', $publicId)->firstOrFail();
        $this->workflowService->assertCurrentApprover($request, $actor);

        if (trim($reason) === '') {
            throw new ApiException('Alasan penolakan wajib diisi.', 422, 'REJECTION_REASON_REQUIRED');
        }

        $request->update([
            'status' => UserRequestStatus::Rejected,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->recordHistory($request, $actor, ApprovalAction::Rejected, $request->current_approval_level, $reason);
        $this->notifications->notifyRequester($request->fresh(['requester']), 'USER_REQUEST_REJECTED',
            'Permintaan user ditolak',
            "Permintaan {$request->request_number} ditolak. Alasan: {$reason}");
        $this->auditLog->record($actor, 'USER_REQUEST_REJECTED', 'UserCreationRequest', $request->id, $request->public_id, null, ['reason' => $reason], $request->company_id);

        return $request;
    }

    public function requestRevision(User $actor, string $publicId, string $notes): UserCreationRequest
    {
        $this->assertPermission($actor, 'iam.request.request_revision');
        $request = UserCreationRequest::query()->where('public_id', $publicId)->firstOrFail();
        $this->workflowService->assertCurrentApprover($request, $actor);

        $request->update([
            'status' => UserRequestStatus::RevisionRequested,
            'revision_notes' => $notes,
            'current_approval_level' => 0,
        ]);

        $this->recordHistory($request, $actor, ApprovalAction::RevisionRequested, 0, $notes);
        $this->notifications->notifyRequester($request->fresh(['requester']), 'USER_REQUEST_REVISION',
            'Permintaan perlu revisi',
            "Permintaan {$request->request_number} perlu direvisi: {$notes}");
        $this->auditLog->record($actor, 'USER_REQUEST_REVISION', 'UserCreationRequest', $request->id, $request->public_id, null, ['notes' => $notes], $request->company_id);

        return $request;
    }

    public function overrideApprove(User $actor, string $publicId): UserCreationRequest
    {
        $this->assertPermission($actor, 'iam.request.override');
        $request = UserCreationRequest::query()->where('public_id', $publicId)->firstOrFail();

        if (in_array($request->status, [UserRequestStatus::Approved, UserRequestStatus::Cancelled], true)) {
            throw new ApiException('Request sudah final.', 422, 'ALREADY_FINAL');
        }

        return DB::transaction(function () use ($actor, $request) {
            $this->recordHistory($request, $actor, ApprovalAction::Overridden, $request->current_approval_level, 'Override approval oleh Company Owner');
            $request->update([
                'status' => UserRequestStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
            $this->provisioningService->createFromRequest($request, $actor);
            $this->notifications->notifyRequester($request->fresh(['requester']), 'USER_REQUEST_APPROVED',
                'Permintaan user disetujui (override)',
                "Permintaan {$request->request_number} disetujui oleh Company Owner.");
            $this->auditLog->record($actor, 'USER_REQUEST_OVERRIDDEN', 'UserCreationRequest', $request->id, $request->public_id, null, ['status' => 'APPROVED'], $request->company_id);

            return $request->fresh(['createdUser']);
        });
    }

    protected function findOwnEditable(User $actor, string $publicId): UserCreationRequest
    {
        $request = UserCreationRequest::query()->where('public_id', $publicId)->firstOrFail();

        if ($request->requested_by !== $actor->id) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }

        if (! in_array($request->status, [UserRequestStatus::Draft, UserRequestStatus::RevisionRequested], true)) {
            throw new ApiException('Request tidak dapat diedit.', 422, 'NOT_EDITABLE');
        }

        return $request;
    }

    protected function assertCanView(User $actor, UserCreationRequest $request): void
    {
        if ($request->requested_by === $actor->id && $actor->hasPermission('iam.request.read.own')) {
            return;
        }
        if ($actor->hasPermission('iam.request.read.all') || $actor->hasPermission('iam.request.approve')) {
            return;
        }
        throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
    }

    protected function recordHistory(UserCreationRequest $request, User $actor, ApprovalAction $action, int $stepOrder, ?string $notes): void
    {
        ApprovalHistory::query()->create([
            'request_id' => $request->id,
            'step_order' => $stepOrder,
            'action' => $action,
            'actor_id' => $actor->id,
            'actor_role_code' => $actor->roles()->first()?->code,
            'notes' => $notes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    protected function resolveDepartment(User $actor, ?string $departmentPublicId = null): Department
    {
        $headDept = $this->roleGuard->resolveDepartmentForHead($actor->id, $actor->default_company_id);
        $isOwner = $actor->roles()->where('code', 'TENANT_OWNER')->exists();
        $canPickDepartment = $isOwner || $actor->hasPermission('iam.department.read');

        if ($departmentPublicId) {
            $department = Department::query()
                ->where('company_id', $actor->default_company_id)
                ->where('public_id', $departmentPublicId)
                ->where('is_active', true)
                ->firstOrFail();

            if ($headDept && $headDept->id !== $department->id && ! $canPickDepartment) {
                throw new ApiException('Anda hanya dapat membuat request untuk departemen Anda.', 403, 'DEPARTMENT_NOT_ALLOWED');
            }

            return $department;
        }

        return $headDept ?? throw new ApiException('Anda bukan Head Divisi yang terdaftar.', 403, 'NOT_DEPARTMENT_HEAD');
    }

    protected function generateRequestNumber(): string
    {
        $year = now()->format('Y');
        $last = UserCreationRequest::query()
            ->where('request_number', 'like', "UCR-{$year}-%")
            ->orderByDesc('id')
            ->value('request_number');

        $seq = 1;
        if ($last && preg_match('/UCR-\d{4}-(\d+)/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('UCR-%s-%06d', $year, $seq);
    }
}