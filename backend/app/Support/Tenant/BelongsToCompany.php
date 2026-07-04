<?php

namespace App\Support\Tenant;

use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $companyId = null;

            if (app()->bound(CompanyContextResolver::class)) {
                $companyId = app(CompanyContextResolver::class)->resolveActiveCompanyId();
            }

            if (! $companyId && auth('api')->check()) {
                $companyId = auth('api')->user()?->default_company_id;
            }

            if ($companyId) {
                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    $companyId,
                );
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}