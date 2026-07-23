<?php

namespace Database\Seeders;

use App\Enums\EnforcementPolicy;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@hopeworks.test'],
            [
                'name' => 'HopeWorks Admin',
                'password' => 'password',
                'is_super_admin' => true,
                'permissions' => [User::PERMISSION_RECORD_MANUAL_PAYMENT],
            ],
        );

        Plan::query()->updateOrCreate(
            ['name' => 'Standard'],
            [
                'price' => 99.00,
                'billing_interval' => 'monthly',
                'storage_limit_gb' => 25,
                'user_limit' => 50,
                'module_eligibility' => [
                    'members' => true,
                    'giving' => true,
                    'events' => true,
                    'communications' => false,
                ],
            ],
        );
    }
}
