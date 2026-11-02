<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'ride.request',
            'ride.offer',
            'vehicle.manage',
            'verification.review',
            'user.suspend',
            'fare.dispute',
            'settings.manage',
            'reports.view',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $user = Role::findOrCreate('user', 'web');
        $user->syncPermissions(['ride.request', 'ride.offer', 'vehicle.manage']);

        $moderator = Role::findOrCreate('moderator', 'web');
        $moderator->syncPermissions(['verification.review', 'fare.dispute', 'reports.view']);

        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions($permissions);
    }
}
