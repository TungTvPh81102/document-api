<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Permissions ──────────────────────────────────────────
        $resources = ['user', 'role', 'permission', 'site', 'plant', 'subsite'];
        $actions   = ['view', 'create', 'update', 'delete', 'manage'];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $name = "{$resource}.{$action}";
                Permission::findOrCreate($name, 'web');
                
                // Update custom fields if needed
                $permission = Permission::where('name', $name)->first();
                $permission->update([
                    'slug'        => Str::slug(str_replace('.', '-', $name)),
                    'resource'    => $resource,
                    'action'      => $action,
                    'description' => "Allow user to {$action} {$resource}",
                    'is_system'   => true,
                ]);
            }
        }

        // ─── Roles ───────────────────────────────────────────────
        
        // 1. Super Admin
        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->update([
            'slug'        => 'super-admin',
            'description' => 'System Super Administrator with all permissions',
            'is_system'   => true,
            'level'       => 1,
            'enabled'     => true,
        ]);
        // Note: Super Admin permissions are usually handled via Gate::before in AuthServiceProvider

        // 2. Admin
        $admin = Role::findOrCreate('admin', 'web');
        $admin->update([
            'slug'        => 'admin',
            'description' => 'System Administrator',
            'is_system'   => true,
            'level'       => 2,
            'enabled'     => true,
        ]);
        $admin->syncPermissions(Permission::all());

        // 3. Manager
        $manager = Role::findOrCreate('manager', 'web');
        $manager->update([
            'slug'        => 'manager',
            'description' => 'Site Manager',
            'is_system'   => false,
            'level'       => 5,
            'enabled'     => true,
        ]);
        $manager->syncPermissions(
            Permission::where('name', 'like', 'site.%')
                ->orWhere('name', 'like', 'plant.%')
                ->orWhere('name', 'like', 'subsite.%')
                ->get()
        );
    }
}
