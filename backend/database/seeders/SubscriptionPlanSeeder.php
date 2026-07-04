<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'STARTER',
                'name' => 'Starter',
                'price_monthly' => 499000,
                'price_yearly' => 4990000,
                'max_users' => 10,
                'max_branches' => 1,
                'max_storage_mb' => 1024,
                'features' => json_encode(['crm', 'ticket', 'wo', 'invoice']),
            ],
            [
                'code' => 'GROWTH',
                'name' => 'Growth',
                'price_monthly' => 1499000,
                'price_yearly' => 14990000,
                'max_users' => 30,
                'max_branches' => 5,
                'max_storage_mb' => 5120,
                'features' => json_encode(['crm', 'sales', 'ops', 'inv', 'fin']),
            ],
            [
                'code' => 'BUSINESS',
                'name' => 'Business',
                'price_monthly' => 3999000,
                'price_yearly' => 39990000,
                'max_users' => 75,
                'max_branches' => 20,
                'max_storage_mb' => 20480,
                'features' => json_encode(['all']),
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('cs_platform_subscription_plans')->updateOrInsert(
                ['code' => $plan['code']],
                array_merge($plan, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }
}