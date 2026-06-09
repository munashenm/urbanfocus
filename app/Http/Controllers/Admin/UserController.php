<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    use LogsAdminActivity;

    public function index(Request $request): View
    {
        $query = User::query()->with('roles')->latest();

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->get('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('slug', $request->get('role')));
        }

        $users = $query->paginate(20)->withQueryString();
        $roles = Role::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User(['is_active' => true]),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'is_admin' => ! empty($validated['roles']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->syncRoles($validated['roles'] ?? []);
        $this->audit('users.create', $user);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        $user->load('roles');

        return view('admin.users.form', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);

        if ($user->id === auth()->id() && ! $request->boolean('is_active')) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        if ($user->id === auth()->id() && empty($validated['roles']) && $user->isSuperAdmin()) {
            return back()->withErrors(['roles' => 'You cannot remove your own super admin role.']);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => $validated['password']]);
        }

        $user->syncRoles($validated['roles'] ?? []);
        $this->audit('users.update', $user);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isSuperAdmin() && User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->count() <= 1) {
            return back()->with('error', 'Cannot delete the last super admin user.');
        }

        $this->audit('users.delete', $user);
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    protected function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email,'.($user?->id ?? 'NULL'),
            'phone' => 'nullable|string|max:30',
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,slug',
            'is_active' => 'boolean',
        ]);
    }
}
