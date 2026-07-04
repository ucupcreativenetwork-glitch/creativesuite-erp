<?php

namespace App\Modules\Platform\Services;

use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\SubscriptionPlan;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Iam\Services\AuditLogService;
use App\Support\Exceptions\ApiException;
use App\Support\Platform\TenantPurgeService;
use Illuminate\Support\Facades\DB;

class PlatformTenantService
{
    public function __construct(
        protected PlatformAdminService $adminService,
        protected TenantPurgeService $purgeService,
        protected AuditLogService $auditLog,
    ) {}

    public function dashboard(): array
    {
        $systemSlug = (string) config('platform.tenant_slug', 'platform');

        $counts = Tenant::query()
            ->where('slug', '!=', $systemSlug)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalTenants = (int) $counts->sum();
        $tenantIds = Tenant::query()->where('slug', '!=', $systemSlug)->pluck('id');
        $totalUsers = (int) User::query()
            ->where('is_platform_admin', false)
            ->whereIn('tenant_id', $tenantIds)
            ->count();

        $recent = $this->tenantQuery()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Tenant $t) => $this->tenantSummary($t));

        return [
            'total_tenants' => $totalTenants,
            'total_users' => $totalUsers,
            'by_status' => [
                'trial' => (int) ($counts[TenantStatus::Trial->value] ?? 0),
                'active' => (int) ($counts[TenantStatus::Active->value] ?? 0),
                'suspended' => (int) ($counts[TenantStatus::Suspended->value] ?? 0),
                'cancelled' => (int) ($counts[TenantStatus::Cancelled->value] ?? 0),
            ],
            'recent_tenants' => $recent,
        ];
    }

    public function list(array $filters = [])
    {
        $query = $this->tenantQuery()->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $query
            ->with('plan')
            ->withCount([
                'users as user_count' => fn ($q) => $q->where('is_platform_admin', false),
                'companies as company_count',
            ])
            ->paginate($filters['per_page'] ?? 25);
    }

    public function show(string $publicId): array
    {
        $tenant = $this->tenantQuery()->where('public_id', $publicId)->firstOrFail();

        return $this->tenantDetail($tenant);
    }

    public function suspend(User $actor, string $publicId): Tenant
    {
        $this->adminService->assertPlatformAdmin($actor);

        $tenant = $this->tenantQuery()->where('public_id', $publicId)->firstOrFail();
        $tenant->update(['status' => TenantStatus::Suspended]);

        return $tenant->fresh();
    }

    public function activate(User $actor, string $publicId): Tenant
    {
        $this->adminService->assertPlatformAdmin($actor);

        $tenant = $this->tenantQuery()->where('public_id', $publicId)->firstOrFail();
        $tenant->update(['status' => TenantStatus::Active]);

        return $tenant->fresh();
    }

    public function purge(User $actor, string $publicId, string $confirmation): array
    {
        $this->adminService->assertPlatformAdmin($actor);

        $tenant = $this->tenantQuery()->where('public_id', $publicId)->firstOrFail();
        $expected = 'DELETE:'.$tenant->slug;

        if ($confirmation !== $expected) {
            throw new ApiException(
                "Konfirmasi tidak valid. Ketik persis: {$expected}",
                422,
                'PURGE_CONFIRMATION_REQUIRED',
            );
        }

        $this->auditLog->record(
            $actor,
            'TENANT_PURGE',
            'Tenant',
            $tenant->id,
            $tenant->public_id,
            [
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'status' => $tenant->status?->value ?? $tenant->status,
            ],
            null,
            null,
        );

        return $this->purgeService->purge($tenant);
    }

    public function purgeDemo(User $actor): array
    {
        $this->adminService->assertPlatformAdmin($actor);

        $slug = (string) config('platform.demo_tenant_slug', 'pt-demo');

        return $this->purgeService->purgeBySlug($slug);
    }

    public function seedDemo(User $actor): array
    {
        $this->adminService->assertPlatformAdmin($actor);

        $slug = (string) config('platform.demo_tenant_slug', 'pt-demo');
        $existed = Tenant::query()->where('slug', $slug)->exists();

        \Illuminate\Support\Facades\Artisan::call('erp:seed-demo');

        $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();

        return [
            'tenant_slug' => $tenant->slug,
            'tenant_name' => $tenant->name,
            'already_existed' => $existed,
            'login' => [
                'company_name' => 'Demo Agency',
                'email' => (string) config('platform.demo_admin_email', 'admin@demo.id'),
            ],
        ];
    }

    public function update(User $actor, string $publicId, array $data): Tenant
    {
        $this->adminService->assertPlatformAdmin($actor);

        $tenant = $this->tenantQuery()->where('public_id', $publicId)->firstOrFail();
        $updates = [];

        if (array_key_exists('plan_code', $data)) {
            if ($data['plan_code'] === null) {
                $updates['plan_id'] = null;
            } else {
                $plan = SubscriptionPlan::query()->where('code', $data['plan_code'])->firstOrFail();
                $updates['plan_id'] = $plan->id;
                $updates['max_users'] = $plan->max_users;
                $updates['max_branches'] = $plan->max_branches;
                $updates['max_storage_mb'] = $plan->max_storage_mb;
            }
        }

        foreach (['max_users', 'max_branches', 'max_storage_mb', 'trial_ends_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if ($updates !== []) {
            $tenant->update($updates);
        }

        return $tenant->fresh(['plan']);
    }

    protected function tenantQuery()
    {
        $systemSlug = (string) config('platform.tenant_slug', 'platform');

        return Tenant::query()->where('slug', '!=', $systemSlug);
    }

    protected function tenantSummary(Tenant $tenant): array
    {
        $tenant->loadMissing('plan');

        return [
            'public_id' => $tenant->public_id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'plan' => $this->planPayload($tenant),
            'user_count' => $tenant->users()->where('is_platform_admin', false)->count(),
            'company_count' => $tenant->companies()->count(),
            'created_at' => $tenant->created_at?->toIso8601String(),
            'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
        ];
    }

    protected function planPayload(Tenant $tenant): ?array
    {
        if (! $tenant->plan) {
            return null;
        }

        return [
            'code' => $tenant->plan->code,
            'name' => $tenant->plan->name,
        ];
    }

    protected function tenantDetail(Tenant $tenant): array
    {
        $summary = $this->tenantSummary($tenant);

        $companies = $tenant->companies()->get(['public_id', 'legal_name', 'trade_name', 'is_active']);

        return array_merge($summary, [
            'max_users' => $tenant->max_users,
            'max_branches' => $tenant->max_branches,
            'max_storage_mb' => $tenant->max_storage_mb,
            'timezone' => $tenant->timezone,
            'locale' => $tenant->locale,
            'companies' => $companies->map(fn ($c) => [
                'public_id' => $c->public_id,
                'legal_name' => $c->legal_name,
                'trade_name' => $c->trade_name,
                'is_active' => $c->is_active,
            ])->all(),
        ]);
    }
}