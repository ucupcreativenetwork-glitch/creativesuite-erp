<?php

namespace App\Modules\Platform\Services;

use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformAdminService
{
    public function ensureSystemTenant(): Tenant
    {
        $slug = (string) config('platform.tenant_slug', 'platform');

        return Tenant::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'public_id' => (string) Str::uuid(),
                'name' => (string) config('platform.system_tenant_name', 'CreativeSuite Platform'),
                'status' => TenantStatus::Active,
                'max_users' => 5,
                'max_branches' => 1,
                'max_storage_mb' => 512,
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id_ID',
            ],
        );
    }

    public function createOrUpdateAdmin(string $email, string $password, ?string $fullName = null): User
    {
        $tenant = $this->ensureSystemTenant();

        return DB::transaction(function () use ($tenant, $email, $password, $fullName) {
            $user = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->first();

            if ($user) {
                $user->update([
                    'password' => $password,
                    'full_name' => $fullName ?? $user->full_name,
                    'is_platform_admin' => true,
                    'is_active' => true,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);

                return $user->fresh();
            }

            return User::query()->create([
                'tenant_id' => $tenant->id,
                'public_id' => (string) Str::uuid(),
                'email' => $email,
                'password' => $password,
                'full_name' => $fullName ?? 'Platform Administrator',
                'is_active' => true,
                'is_platform_admin' => true,
                'email_verified_at' => now(),
            ]);
        });
    }

    public function assertPlatformAdmin(User $user): void
    {
        if (! $user->is_platform_admin) {
            throw new ApiException('Platform administrator access required.', 403, 'FORBIDDEN');
        }
    }
}