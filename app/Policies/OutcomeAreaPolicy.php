<?php

namespace App\Policies;

use App\Models\OutcomeArea;
use App\Models\User;

class OutcomeAreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('outcome-areas.view');
    }

    public function view(User $user, OutcomeArea $outcomeArea): bool
    {
        return $user->can('outcome-areas.view');
    }

    public function create(User $user): bool
    {
        return $user->can('outcome-areas.create');
    }

    public function update(User $user, OutcomeArea $outcomeArea): bool
    {
        return $user->can('outcome-areas.update');
    }

    public function delete(User $user, OutcomeArea $outcomeArea): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
