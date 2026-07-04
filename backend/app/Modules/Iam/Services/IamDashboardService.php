<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\User;
use App\Modules\Iam\Enums\UserRequestStatus;
use App\Modules\Iam\Models\UserCreationRequest;
use App\Support\Business\ChecksPermissions;

class IamDashboardService
{
    use ChecksPermissions;

    public function headStats(User $actor): array
    {
        $this->assertPermission($actor, 'iam.request.read.own');

        $base = UserCreationRequest::query()->where('requested_by', $actor->id);

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->whereIn('status', [UserRequestStatus::Pending, UserRequestStatus::InReview])->count(),
            'approved' => (clone $base)->where('status', UserRequestStatus::Approved)->count(),
            'rejected' => (clone $base)->where('status', UserRequestStatus::Rejected)->count(),
        ];
    }

    public function approverStats(User $actor): array
    {
        $this->assertPermission($actor, 'iam.request.approve');

        $today = now()->startOfDay();
        $pending = UserCreationRequest::query()
            ->whereIn('status', [UserRequestStatus::Pending, UserRequestStatus::InReview])
            ->count();

        $approvedToday = UserCreationRequest::query()
            ->where('status', UserRequestStatus::Approved)
            ->where('approved_at', '>=', $today)
            ->where('approved_by', $actor->id)
            ->count();

        $rejectedToday = UserCreationRequest::query()
            ->where('status', UserRequestStatus::Rejected)
            ->where('rejected_at', '>=', $today)
            ->where('rejected_by', $actor->id)
            ->count();

        return [
            'pending_approval' => $pending,
            'approved_today' => $approvedToday,
            'rejected_today' => $rejectedToday,
        ];
    }

    public function ownerStats(User $actor): array
    {
        $this->assertPermission($actor, 'iam.request.read.all');

        $total = UserCreationRequest::query()->count();
        $approved = UserCreationRequest::query()->where('status', UserRequestStatus::Approved)->count();
        $pending = UserCreationRequest::query()->whereIn('status', [UserRequestStatus::Pending, UserRequestStatus::InReview])->count();

        $monthStart = now()->startOfMonth();
        $userGrowth = User::query()
            ->where('provisioning_source', 'REQUEST_APPROVAL')
            ->where('created_at', '>=', $monthStart)
            ->count();

        return [
            'total_requests' => $total,
            'pending' => $pending,
            'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 1) : 0,
            'user_growth_this_month' => $userGrowth,
        ];
    }
}