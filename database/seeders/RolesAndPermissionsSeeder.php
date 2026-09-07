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
        Permission::create(['name' => 'create posts']);
        Permission::create(['name' => 'edit posts']);
        Permission::create(['name' => 'delete posts']);
        Permission::create(['name' => 'publish posts']);

        // Create role and assign permissions
        $roleEditor = Role::create(['name' => 'editor']);
        $roleEditor->givePermissionTo(['create posts', 'edit posts', 'publish posts']);

        // The Admin role has a special permission that gives all abilities
        $roleAdmin = Role::create(['name' => 'admin']);
        // No need to assign all permissions to Admin, as we will make them super-admin via Gate

        // Role for regular users
        Role::create(['name' => 'user']);
    }
}
