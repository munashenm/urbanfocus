<?php

namespace App\Concerns;

use App\Models\Permission;
use App\Models\Role;
use App\Support\AdminRbac;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasAdminRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function syncRoles(array $roleSlugs): void
    {
        if (! AdminRbac::ready()) {
            $this->update(['is_admin' => $roleSlugs !== []]);

            return;
        }

        $ids = Role::whereIn('slug', $roleSlugs)->pluck('id');
        $this->roles()->sync($ids);
        $this->is_admin = $this->roles()->exists();
        $this->save();
    }

    public function isSuperAdmin(): bool
    {
        if (! AdminRbac::ready()) {
            return (bool) $this->is_admin;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('slug', 'super-admin');
        }

        return $this->roles()->where('slug', 'super-admin')->exists();
    }

    public function canAccessAdmin(): bool
    {
        if (! AdminRbac::userIsActive($this)) {
            return false;
        }

        if ($this->is_admin) {
            return true;
        }

        if (! AdminRbac::ready()) {
            return false;
        }

        return $this->roles()->exists();
    }

    public function hasPermission(string $permission): bool
    {
        if (! AdminRbac::ready()) {
            return (bool) $this->is_admin;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->is_admin && $this->roles()->count() === 0) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function isLockedOut(): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'locked_until')) {
            return false;
        }

        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function recordFailedLogin(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'failed_login_attempts')) {
            return;
        }

        $maxAttempts = config('permissions.login.max_attempts', 5);
        $lockoutMinutes = config('permissions.login.lockout_minutes', 15);

        $attempts = $this->failed_login_attempts + 1;
        $updates = ['failed_login_attempts' => $attempts];

        if ($attempts >= $maxAttempts && \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'locked_until')) {
            $updates['locked_until'] = now()->addMinutes($lockoutMinutes);
        }

        $this->update($updates);
    }

    public function recordSuccessfulLogin(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'last_login_at')) {
            return;
        }

        $updates = [
            'last_login_at' => now(),
            'failed_login_attempts' => 0,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'locked_until')) {
            $updates['locked_until'] = null;
        }

        $this->update($updates);
    }

    public function roleLabels(): string
    {
        if (! AdminRbac::ready()) {
            return $this->is_admin ? 'Administrator' : 'Customer';
        }

        $labels = $this->roles()->pluck('name');

        if ($labels->isEmpty() && $this->is_admin) {
            return 'Administrator';
        }

        return $labels->implode(', ') ?: 'Customer';
    }
}
