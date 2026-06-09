<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    use LogsAdminActivity;

    public function index(): View
    {
        $roles = Role::withCount('users')->with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('group')->orderBy('label')->get()->groupBy('group');

        return view('admin.roles.index', compact('roles', 'permissions'));
    }

    public function edit(Role $role): View
    {
        $role->load('permissions');
        $permissions = Permission::orderBy('group')->orderBy('label')->get()->groupBy('group');

        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->is_system && $role->slug === 'super-admin') {
            return back()->with('error', 'Super Admin permissions cannot be changed.');
        }

        $validated = $request->validate([
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if (! $role->is_system) {
            $role->update(['description' => $validated['description'] ?? null]);
        }

        if (! ($role->is_system && $role->slug === 'super-admin')) {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        $this->audit('roles.update', $role);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }
}
