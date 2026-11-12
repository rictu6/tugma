<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('RICTU');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole('RICTU');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('RICTU');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole('RICTU');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole('RICTU') && ! $user->is($model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('RICTU');
    }
}
