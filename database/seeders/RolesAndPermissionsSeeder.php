<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset the cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        Permission::findOrCreate('create posts');
        Permission::findOrCreate('edit posts');
        Permission::findOrCreate('delete posts');
        Permission::findOrCreate('publish posts');

        // Create role and assign permissions
        $roleEditor = Role::findOrCreate('editor');
        $roleEditor->givePermissionTo(['create posts', 'edit posts', 'publish posts']);

        // The Admin role
        Role::findOrCreate('admin');

        // Role for regular users
        Role::findOrCreate('user');
    }
}
