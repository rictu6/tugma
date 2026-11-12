<?php

namespace App\Policies;

use App\Models\AllotmentEntry;
use App\Models\User;

class AllotmentEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('allotment-entries.view');
    }

    public function view(User $user, AllotmentEntry $allotmentEntry): bool
    {
        return $user->can('allotment-entries.view');
    }

    public function create(User $user): bool
    {
        return $user->can('allotment-entries.create');
    }

    public function update(User $user, AllotmentEntry $allotmentEntry): bool
    {
        return $user->can('allotment-entries.update');
    }
}
