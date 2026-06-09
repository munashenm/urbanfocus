<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('permissions.permissions', []) as $name => $meta) {
            Permission::updateOrCreate(
                ['name' => $name],
                [
                    'label' => $meta['label'] ?? $name,
                    'group' => $meta['group'] ?? 'general',
                ]
            );
        }

        $allPermissions = Permission::pluck('name')->all();

        foreach (config('permissions.roles', []) as $slug => $meta) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['label'] ?? ucfirst(str_replace('-', ' ', $slug)),
                    'description' => $meta['description'] ?? null,
                    'is_system' => $meta['system'] ?? false,
                ]
            );

            $permissionNames = ($meta['permissions'] ?? []) === ['*']
                ? $allPermissions
                : ($meta['permissions'] ?? []);

            $role->syncPermissions($permissionNames);
        }

        $superAdmin = User::where('email', 'admin@urbanfocus.co.za')->first();
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        if ($superAdmin && $superAdminRole && ! $superAdmin->roles()->where('roles.id', $superAdminRole->id)->exists()) {
            $superAdmin->roles()->syncWithoutDetaching([$superAdminRole->id]);
            $superAdmin->update(['is_admin' => true, 'is_active' => true]);
        }
    }
}
