<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('RICTU');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->hasRole('RICTU') && $permission->guard_name === 'web';
    }

    public function create(User $user): bool
    {
        return $user->hasRole('RICTU');
    }

    public function update(User $user, Permission $permission): bool
    {
        return false;
    }

    public function delete(User $user, Permission $permission): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
