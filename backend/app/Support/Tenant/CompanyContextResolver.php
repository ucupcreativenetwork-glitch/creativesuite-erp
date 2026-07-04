<?php

namespace App\Support\Tenant;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyAccess;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\Request;

class CompanyContextResolver
{
    public function __construct(
        protected CompanyManager $companyManager,
    ) {}

    public function resolveActiveCompanyId(): ?int
    {
        if ($this->companyManager->id() !== null) {
            return $this->companyManager->id();
        }

        $request = request();
        if (! $request) {
            return null;
        }

        if ($request->attributes->has('integration_api_key')) {
            $companyId = (int) $request->attributes->get('integration_api_key')->company_id;
            $this->companyManager->set($companyId);

            return $companyId;
        }

        $user = auth('api')->user();
        if (! $user instanceof User) {
            return null;
        }

        $header = $request->header('X-Company-ID');
        $companyId = $this->resolveCompanyIdForUser($user, $header);
        $this->companyManager->set($companyId);

        return $companyId;
    }

    public function resolveCompanyIdForUser(User $user, ?string $header): int
    {
        $companyId = $this->resolveCompanyIdentifier($user, $header);

        if (! $this->userCanAccessCompany($user, $companyId)) {
            throw new ApiException(
                'You do not have access to this company.',
                403,
                'COMPANY_ACCESS_DENIED',
            );
        }

        return $companyId;
    }

    protected function resolveCompanyIdentifier(User $user, ?string $header): int
    {
        if ($header !== null && $header !== '') {
            $company = $this->findCompanyByIdentifier($header, $user->tenant_id);

            if (! $company) {
                throw new ApiException('Company not found.', 404, 'COMPANY_NOT_FOUND');
            }

            return $company->id;
        }

        if (! $user->default_company_id) {
            throw new ApiException(
                'No company context. Send X-Company-ID header or set a default company.',
                403,
                'COMPANY_CONTEXT_REQUIRED',
            );
        }

        return (int) $user->default_company_id;
    }

    protected function findCompanyByIdentifier(string $identifier, int $tenantId): ?Company
    {
        $query = Company::query()->where('tenant_id', $tenantId);

        if (is_numeric($identifier)) {
            return $query->where('id', (int) $identifier)->first();
        }

        return $query->where('public_id', $identifier)->first();
    }

    protected function userCanAccessCompany(User $user, int $companyId): bool
    {
        if ($user->is_platform_admin) {
            return Company::query()
                ->where('id', $companyId)
                ->where('tenant_id', $user->tenant_id)
                ->exists();
        }

        if ($user->isTenantAdministrator()) {
            return Company::query()
                ->where('id', $companyId)
                ->where('tenant_id', $user->tenant_id)
                ->exists();
        }

        if ((int) $user->default_company_id === $companyId) {
            return true;
        }

        return UserCompanyAccess::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->exists();
    }
}