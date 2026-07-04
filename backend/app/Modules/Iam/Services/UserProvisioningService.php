<?php

namespace App\Modules\Iam\Services;

use App\Modules\Auth\Enums\UserAccountStatus;
use App\Modules\Auth\Services\UserActivationService;
use App\Modules\Business\Services\EmployeeLinkService;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\Iam\Models\UserCreationRequest;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserProvisioningService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected AuditLogService $auditLog,
        protected UserActivationService $activationService,
        protected NotificationDispatcher $notifications,
        protected EmployeeLinkService $employeeLink,
    ) {}

    public function createFromRequest(UserCreationRequest $request, User $actor): User
    {
        if ($this->userRepository->emailExistsInTenant($request->tenant_id, $request->email)) {
            throw new ApiException('Email sudah terdaftar di tenant ini.', 409, 'EMAIL_TAKEN');
        }

        $tenant = Tenant::query()->findOrFail($request->tenant_id);
        $slotCount = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('account_status', [
                UserAccountStatus::Rejected->value,
                UserAccountStatus::Disabled->value,
            ])
            ->count();

        if ($slotCount >= $tenant->max_users) {
            throw new ApiException('Batas maksimum user tenant telah tercapai.', 422, 'MAX_USERS_REACHED');
        }

        $placeholderPassword = Str::password(32);

        return DB::transaction(function () use ($request, $actor, $placeholderPassword) {
            $user = $this->userRepository->create([
                'tenant_id' => $request->tenant_id,
                'public_id' => (string) Str::uuid(),
                'email' => $request->email,
                'password' => $placeholderPassword,
                'full_name' => $request->full_name,
                'phone' => $request->phone,
                'default_company_id' => $request->company_id,
                'default_branch_id' => $request->branch_id,
                'department_id' => $request->department_id,
                'position' => $request->position,
                'direct_manager_id' => $request->direct_manager_id,
                'provisioning_source' => 'REQUEST_APPROVAL',
                'provisioned_from_request_id' => $request->id,
                'must_change_password' => false,
                'account_status' => UserAccountStatus::PendingActivation->value,
                'is_active' => false,
                'activated_at' => null,
            ]);

            $this->userRepository->assignRole($user, $request->requested_role_id, $request->branch_id);
            $this->userRepository->grantCompanyAccess($user, $request->company_id, true);

            $request->update(['created_user_id' => $user->id]);

            $token = $this->activationService->createActivationToken($user);
            $this->activationService->sendActivationNotifications($user, $token, $actor);

            $this->notifications->notifyUsers(
                collect([$user]),
                'ACCOUNT_APPROVED',
                'Akun disetujui — aktivasi diperlukan',
                'Akun Anda telah disetujui. Cek email untuk link aktivasi (berlaku 24 jam).',
                sendEmail: false,
            );

            $this->employeeLink->ensureForUser($user->fresh());

            $this->auditLog->record(
                $actor,
                'USER_PROVISIONED',
                'User',
                $user->id,
                $user->public_id,
                null,
                [
                    'email' => $user->email,
                    'from_request' => $request->request_number,
                    'account_status' => UserAccountStatus::PendingActivation->value,
                ],
                $request->company_id,
            );

            return $user;
        });
    }
}