<?php

namespace App\Modules\Auth\Services;

use App\Modules\Core\Models\User;
use App\Modules\Iam\Services\AuditLogService;
use App\Modules\Iam\Services\NotificationDispatcher;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Hash;

class ChangePasswordService
{
    public function __construct(
        protected AuditLogService $auditLog,
        protected NotificationDispatcher $notifications,
    ) {}

    public function change(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new ApiException('Password saat ini tidak valid.', 422, 'INVALID_CURRENT_PASSWORD');
        }

        $user->update([
            'password' => $newPassword,
            'must_change_password' => false,
        ]);

        $this->auditLog->record(
            $user,
            'PASSWORD_CHANGED',
            'User',
            $user->id,
            $user->public_id,
            null,
            ['via' => 'change_password'],
            $user->default_company_id,
        );

        $this->notifications->notifyUsers(
            collect([$user]),
            'PASSWORD_CHANGED',
            'Password berhasil diubah',
            'Password akun CreativeSuite ERP Anda telah berhasil diperbarui.',
        );
    }
}