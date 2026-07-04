<?php

namespace App\Support\Tenant;

use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function ($model): void {
            if (empty($model->tenant_id) && app()->bound(TenantManager::class)) {
                $tenant = app(TenantManager::class)->get();
                if ($tenant) {
                    $model->tenant_id = $tenant->id;
                }
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (! app()->bound(TenantManager::class)) {
                return;
            }

            $tenant = app(TenantManager::class)->get();
            if ($tenant) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenant->id);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}