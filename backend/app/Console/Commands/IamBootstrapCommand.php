<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tenant;
use App\Modules\Iam\Services\IamBootstrapService;
use App\Support\Tenant\TenantManager;
use Illuminate\Console\Command;

class IamBootstrapCommand extends Command
{
    protected $signature = 'iam:bootstrap {tenant_slug}';

    protected $description = 'Bootstrap IAM departments, roles, workflows for a tenant';

    public function handle(IamBootstrapService $bootstrap, TenantManager $tenantManager): int
    {
        $tenant = Tenant::query()->where('slug', $this->argument('tenant_slug'))->firstOrFail();
        $tenantManager->set($tenant);

        $company = Company::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $bootstrap->bootstrapTenant($tenant, $company);

        $this->info("IAM bootstrap completed for tenant: {$tenant->slug}");

        return self::SUCCESS;
    }
}