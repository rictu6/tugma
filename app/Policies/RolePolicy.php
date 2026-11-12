<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('RICTU');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasRole('RICTU') && $role->guard_name === 'web';
    }

    public function create(User $user): bool
    {
        return $user->hasRole('RICTU');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasRole('RICTU') && $role->guard_name === 'web';
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasRole('RICTU')
            && $role->guard_name === 'web'
            && ! in_array($role->name, ['RICTU', 'SRMU', 'BUDGET'], true)
            && ! DB::table(config('permission.table_names.model_has_roles'))
                ->where(config('permission.column_names.role_pivot_key') ?: 'role_id', $role->getKey())
                ->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('RICTU');
    }
}
