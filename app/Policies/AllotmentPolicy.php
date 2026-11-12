<?php

namespace App\Policies;

use App\Models\Allotment;
use App\Models\User;

class AllotmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('allotments.view');
    }

    public function view(User $user, Allotment $allotment): bool
    {
        return $user->can('allotments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('allotments.create');
    }

    public function update(User $user, Allotment $allotment): bool
    {
        return $user->can('allotments.update');
    }
}
