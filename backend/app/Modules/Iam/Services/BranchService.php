<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Collection;

class BranchService
{
    use ChecksPermissions;

    public function list(User $user): Collection
    {
        $this->assertAnyPermission($user, ['core.company.read', 'iam.request.create']);

        return Branch::query()
            ->where('company_id', $user->default_company_id)
            ->where('is_active', true)
            ->orderByDesc('is_head_office')
            ->orderBy('name')
            ->get();
    }

    public function show(User $user, int $branchId): Branch
    {
        $this->assertPermission($user, 'core.company.update');

        return $this->resolveBranch($user, $branchId);
    }

    public function update(User $user, int $branchId, array $data): Branch
    {
        $this->assertPermission($user, 'core.company.update');

        $branch = $this->resolveBranch($user, $branchId);

        if (
            array_key_exists('attendance_geofence_enabled', $data)
            && ! $data['attendance_geofence_enabled']
        ) {
            $data['attendance_latitude'] = null;
            $data['attendance_longitude'] = null;
        }

        $branch->update($data);

        return $branch->fresh();
    }

    protected function resolveBranch(User $user, int $branchId): Branch
    {
        $branch = Branch::query()
            ->where('id', $branchId)
            ->where('company_id', $user->default_company_id)
            ->first();

        if (! $branch) {
            throw new ApiException('Cabang tidak ditemukan.', 404, 'BRANCH_NOT_FOUND');
        }

        return $branch;
    }

    protected function assertAnyPermission(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return;
            }
        }

        throw new ApiException('Anda tidak memiliki akses ke data cabang.', 403, 'FORBIDDEN');
    }
}