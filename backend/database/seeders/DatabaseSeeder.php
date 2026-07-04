<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SubscriptionPlanSeeder::class,
            PermissionSeeder::class,
        ]);

        if (filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOLEAN) || app()->environment('local')) {
            $this->call(DemoAgencySeeder::class);
        }
    }
}