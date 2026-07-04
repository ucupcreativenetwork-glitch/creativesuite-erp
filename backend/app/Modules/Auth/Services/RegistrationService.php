<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Notifications\VerifyEmailNotification;
use App\Modules\Auth\Services\Contracts\RegistrationServiceInterface;
use App\Modules\Core\Enums\EntityType;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\User;
use App\Modules\Core\Repositories\Contracts\BranchRepositoryInterface;
use App\Modules\Core\Repositories\Contracts\CompanyRepositoryInterface;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use App\Modules\Core\Repositories\Contracts\TenantRepositoryInterface;
use App\Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\Business\Services\EmployeeLinkService;
use App\Modules\Finance\Services\CoaSetupService;
use App\Support\Exceptions\ApiException;
use App\Support\Tenant\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class RegistrationService implements RegistrationServiceInterface
{
    public function __construct(
        protected TenantRepositoryInterface $tenantRepository,
        protected CompanyRepositoryInterface $companyRepository,
        protected BranchRepositoryInterface $branchRepository,
        protected UserRepositoryInterface $userRepository,
        protected RoleRepositoryInterface $roleRepository,
        protected TenantManager $tenantManager,
        protected CoaSetupService $coaSetupService,
        protected EmployeeLinkService $employeeLink,
    ) {}

    public function registerCompany(array $data): array
    {
        if ($this->tenantRepository->slugExists($data['tenant_slug'])) {
            throw new ApiException('Tenant slug already exists.', 409, 'TENANT_SLUG_TAKEN');
        }

        return DB::transaction(function () use ($data) {
            $tenant = $this->tenantRepository->create([
                'public_id' => (string) Str::uuid(),
                'name' => $data['company_name'],
                'slug' => $data['tenant_slug'],
                'status' => TenantStatus::Trial,
                'max_users' => 10,
                'max_branches' => 1,
                'max_storage_mb' => 1024,
                'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
                'locale' => $data['locale'] ?? 'id_ID',
                'trial_ends_at' => now()->addDays(14),
            ]);

            $this->tenantManager->set($tenant);

            $company = $this->companyRepository->create([
                'tenant_id' => $tenant->id,
                'public_id' => (string) Str::uuid(),
                'legal_name' => $data['legal_name'] ?? $data['company_name'],
                'trade_name' => $data['company_name'],
                'entity_type' => $data['entity_type'] ?? EntityType::Pt,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            $branch = $this->branchRepository->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'code' => 'HQ',
                'name' => 'Head Office',
                'is_head_office' => true,
                'is_active' => true,
            ]);

            $ownerRole = $this->roleRepository->findByCode($tenant->id, 'TENANT_OWNER');
            if (! $ownerRole) {
                $ownerRole = $this->roleRepository->create([
                    'tenant_id' => $tenant->id,
                    'code' => 'TENANT_OWNER',
                    'name' => 'Tenant Owner',
                    'description' => 'Full access to tenant resources',
                    'is_system' => true,
                    'is_active' => true,
                ]);

                $permissionIds = Permission::query()->pluck('id');
                $ownerRole->permissions()->sync($permissionIds);
            }

            $user = $this->userRepository->create([
                'tenant_id' => $tenant->id,
                'public_id' => (string) Str::uuid(),
                'email' => $data['email'],
                'password' => $data['password'],
                'full_name' => $data['full_name'],
                'phone' => $data['phone'] ?? null,
                'default_company_id' => $company->id,
                'default_branch_id' => $branch->id,
                'is_active' => true,
            ]);

            $this->userRepository->assignRole($user, $ownerRole->id);
            $this->userRepository->grantCompanyAccess($user, $company->id, true);

            $this->coaSetupService->setupForCompany($tenant->id, $company->id);

            $this->employeeLink->ensureForUser($user);

            $user->notify(new VerifyEmailNotification);

            $token = JWTAuth::fromUser($user);

            return $this->buildAuthPayload($user, $token, $tenant, $company, $branch);
        });
    }

    public function registerUser(array $data): array
    {
        /** @var User $authUser */
        $authUser = auth('api')->user();

        if (! $authUser->hasPermission('core.user.create')) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }

        if ($this->userRepository->emailExistsInTenant($authUser->tenant_id, $data['email'])) {
            throw new ApiException('Email already registered in this tenant.', 409, 'EMAIL_TAKEN');
        }

        return DB::transaction(function () use ($data, $authUser) {
            $companyId = $data['company_id'] ?? $authUser->default_company_id;
            $branchId = $data['branch_id'] ?? $authUser->default_branch_id;

            $user = $this->userRepository->create([
                'tenant_id' => $authUser->tenant_id,
                'public_id' => (string) Str::uuid(),
                'email' => $data['email'],
                'password' => $data['password'],
                'full_name' => $data['full_name'],
                'phone' => $data['phone'] ?? null,
                'default_company_id' => $companyId,
                'default_branch_id' => $branchId,
                'is_active' => true,
            ]);

            if (! empty($data['role_code'])) {
                $role = $this->roleRepository->findByCode($authUser->tenant_id, $data['role_code']);
                if ($role) {
                    $this->userRepository->assignRole($user, $role->id, $branchId);
                }
            }

            if ($companyId) {
                $this->userRepository->grantCompanyAccess($user, $companyId, true);
            }

            $this->employeeLink->ensureForUser($user);

            $user->notify(new VerifyEmailNotification);

            return [
                'user' => $user->load(['roles', 'defaultCompany', 'defaultBranch']),
            ];
        });
    }

    protected function buildAuthPayload(User $user, string $token, $tenant, $company, $branch): array
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $user->load(['roles', 'defaultCompany', 'defaultBranch']),
            'tenant' => $tenant,
            'company' => $company,
            'branch' => $branch,
        ];
    }
}