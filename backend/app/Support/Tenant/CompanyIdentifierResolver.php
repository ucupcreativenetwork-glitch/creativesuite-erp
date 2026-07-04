<?php

namespace App\Support\Tenant;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Repositories\Contracts\TenantRepositoryInterface;

class CompanyIdentifierResolver
{
    public function __construct(
        protected TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * @return array{tenant: ?Tenant, ambiguous: bool}
     */
    public function resolve(string $identifier): array
    {
        $normalized = $this->normalize($identifier);

        if ($normalized === '') {
            return ['tenant' => null, 'ambiguous' => false];
        }

        $platformTenant = $this->resolvePlatformAlias($normalized);

        if ($platformTenant) {
            return ['tenant' => $platformTenant, 'ambiguous' => false];
        }

        $demoTenant = $this->resolveDemoAlias($normalized);

        if ($demoTenant) {
            return ['tenant' => $demoTenant, 'ambiguous' => false];
        }

        $slugCandidate = $this->toSlug($normalized);
        $bySlug = $this->tenantRepository->findBySlug($slugCandidate);

        if ($bySlug) {
            return ['tenant' => $bySlug, 'ambiguous' => false];
        }

        $matches = collect();

        Tenant::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->get()
            ->each(fn (Tenant $tenant) => $matches->put($tenant->id, $tenant));

        Company::query()
            ->where(function ($query) use ($normalized) {
                $query->whereRaw('LOWER(trade_name) = ?', [$normalized])
                    ->orWhereRaw('LOWER(legal_name) = ?', [$normalized]);
            })
            ->with('tenant')
            ->get()
            ->each(function (Company $company) use ($matches) {
                if ($company->tenant) {
                    $matches->put($company->tenant->id, $company->tenant);
                }
            });

        if ($matches->isEmpty()) {
            return ['tenant' => null, 'ambiguous' => false];
        }

        if ($matches->count() === 1) {
            return ['tenant' => $matches->first(), 'ambiguous' => false];
        }

        return ['tenant' => null, 'ambiguous' => true];
    }

    protected function normalize(string $identifier): string
    {
        $value = mb_strtolower(trim($identifier));

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }

    protected function toSlug(string $normalized): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';

        return trim($slug, '-');
    }

    protected function resolvePlatformAlias(string $normalized): ?Tenant
    {
        $platformSlug = mb_strtolower((string) config('platform.tenant_slug', 'platform'));
        $aliases = collect(config('platform.login_aliases', []))
            ->push(
                (string) config('platform.system_tenant_name', 'CreativeSuite Platform'),
                $platformSlug,
                'admin saas',
                'platform admin',
            )
            ->map(fn (string $alias) => $this->normalize($alias))
            ->unique()
            ->values();

        if (! $aliases->contains($normalized) && $normalized !== $platformSlug) {
            return null;
        }

        return $this->tenantRepository->findBySlug($platformSlug);
    }

    protected function resolveDemoAlias(string $normalized): ?Tenant
    {
        $demoSlug = mb_strtolower((string) config('platform.demo_tenant_slug', 'pt-demo'));
        $aliases = collect(config('platform.demo_login_aliases', []))
            ->push($demoSlug)
            ->map(fn (string $alias) => $this->normalize($alias))
            ->unique()
            ->values();

        if (! $aliases->contains($normalized) && $normalized !== $demoSlug) {
            return null;
        }

        return $this->tenantRepository->findBySlug($demoSlug);
    }
}