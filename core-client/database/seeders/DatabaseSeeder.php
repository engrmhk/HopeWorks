<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\User;
use App\Services\ModuleRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'Synod Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Church Admin', 'guard_name' => 'web']);

        $synod = Church::query()->firstOrCreate(
            ['slug' => 'demo-synod'],
            [
                'name' => 'Demo Synod',
                'synod_instance' => true,
                'is_active' => true,
            ]
        );

        $church = Church::query()->firstOrCreate(
            ['slug' => 'demo-church'],
            [
                'parent_id' => $synod->id,
                'name' => 'Demo Church',
                'synod_instance' => false,
                'is_active' => true,
            ]
        );

        app(ModuleRegistry::class)->syncManifestsToDatabase();
        app(ModuleRegistry::class)->seedChurchModules($church, ['attendance']);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@hopeworks.test'],
            [
                'church_id' => $church->id,
                'name' => 'Church Admin',
                'password' => Hash::make('password'),
                'onboarding_completed_at' => now(),
            ]
        );
        $admin->assignRole('Church Admin');
    }
}
