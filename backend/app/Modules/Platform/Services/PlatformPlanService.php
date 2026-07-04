<?php

namespace App\Modules\Platform\Services;

use App\Modules\Core\Models\SubscriptionPlan;

class PlatformPlanService
{
    public function list()
    {
        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('price_monthly')
            ->get();
    }
}