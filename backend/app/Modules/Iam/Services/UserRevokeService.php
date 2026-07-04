<?php

namespace App\Modules\Iam\Services;

use App\Modules\Auth\Enums\UserAccountStatus;
use App\Modules\Core\Models\User;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

class UserRevokeService
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function revoke(User $actor, string $targetPublicId): User
    {
        if ($actor->public_id === $targetPublicId) {
            throw new ApiException('Tidak dapat mencabut akses akun sendiri.', 422, 'CANNOT_REVOKE_SELF');
        }

        $target = User::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('public_id', $targetPublicId)
            ->with('roles')
            ->firstOrFail();

        if ($target->roles->contains('code', 'TENANT_OWNER')) {
            throw new ApiException('Tidak dapat menghapus akun Tenant Owner.', 422, 'CANNOT_DELETE_OWNER');
        }

        if ($target->provisioning_source !== 'REQUEST_APPROVAL') {
            throw new ApiException('User ini tidak dibuat melalui IAM request.', 422, 'NOT_PROVISIONED_USER');
        }

        if ($target->account_status === UserAccountStatus::Disabled->value) {
            throw new ApiException('User sudah tidak aktif.', 422, 'ALREADY_REVOKED');
        }

        return DB::transaction(function () use ($actor, $target) {
            $target->roles()->detach();
            $target->companies()->detach();
            $target->update([
                'is_active' => false,
                'account_status' => UserAccountStatus::Disabled->value,
                'must_change_password' => true,
            ]);

            $this->auditLog->record(
                $actor,
                'USER_REVOKED',
                'User',
                $target->id,
                $target->public_id,
                ['is_active' => true],
                ['is_active' => false, 'provisioning_source' => $target->provisioning_source],
                $actor->default_company_id,
            );

            return $target->fresh(['roles']);
        });
    }
}