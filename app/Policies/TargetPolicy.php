<?php

namespace App\Policies;

use App\Models\Target;
use App\Models\User;

class TargetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('targets.view');
    }

    public function view(User $user, Target $target): bool
    {
        return $user->can('targets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('targets.create');
    }

    public function update(User $user, Target $target): bool
    {
        return $user->can('targets.update');
    }

    public function delete(User $user, Target $target): bool
    {
        return $user->can('targets.delete')
            && ! $target->performanceIndicators()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('targets.delete');
    }
}
