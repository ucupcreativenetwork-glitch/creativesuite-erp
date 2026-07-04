<?php

namespace App\Support\Business;

use App\Modules\Core\Models\User;

class HrLeadership
{
    /** @return list<string> */
    public static function leaderRoleCodes(): array
    {
        return config('hr.leader_role_codes', []);
    }

    public static function isLeader(User $user): bool
    {
        if ($user->isTenantAdministrator()) {
            return true;
        }

        $codes = self::leaderRoleCodes();

        return $user->roles()->whereIn('code', $codes)->exists();
    }

    public static function canManageHr(User $user): bool
    {
        return self::isLeader($user) || $user->hasPermission('hr.leave.manage');
    }

    public static function canApproveLeave(User $user): bool
    {
        return self::isLeader($user) || $user->hasPermission('hr.leave.approve');
    }
}