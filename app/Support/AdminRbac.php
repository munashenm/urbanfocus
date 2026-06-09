<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class AdminRbac
{
    public static function ready(): bool
    {
        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        try {
            $ready = Schema::hasTable('roles')
                && Schema::hasTable('permissions')
                && Schema::hasTable('role_permission')
                && Schema::hasTable('user_role');
        } catch (\Throwable) {
            $ready = false;
        }

        return $ready;
    }

    public static function customersQuery(): Builder
    {
        $query = User::query()->where('is_admin', false);

        if (self::ready()) {
            $query->whereDoesntHave('roles');
        }

        return $query;
    }

    public static function userIsActive(User $user): bool
    {
        if (! Schema::hasColumn($user->getTable(), 'is_active')) {
            return true;
        }

        return (bool) $user->is_active;
    }
}
