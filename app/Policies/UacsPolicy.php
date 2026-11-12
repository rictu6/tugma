<?php

namespace App\Policies;

use App\Models\Uacs;
use App\Models\User;

class UacsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('uacs.view');
    }

    public function view(User $user, Uacs $uacs): bool
    {
        return $user->can('uacs.view');
    }

    public function create(User $user): bool
    {
        return $user->can('uacs.create');
    }

    public function update(User $user, Uacs $uacs): bool
    {
        return $user->can('uacs.update');
    }

    public function delete(User $user, Uacs $uacs): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
