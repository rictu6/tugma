<?php

namespace App\Policies;

use App\Models\Accomplishment;
use App\Models\User;

class AccomplishmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('accomplishments.view')
            && $user->can('performance-indicators.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Accomplishment $accomplishment): bool
    {
        $performanceIndicator = $accomplishment->performanceIndicator;

        return $user->can('accomplishments.view')
            && $performanceIndicator !== null
            && $user->can('view', $performanceIndicator);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('accomplishments.create')
            && $user->can('performance-indicators.view');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Accomplishment $accomplishment): bool
    {
        $performanceIndicator = $accomplishment->performanceIndicator;

        return $user->can('accomplishments.update')
            && $performanceIndicator !== null
            && $user->can('view', $performanceIndicator);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Accomplishment $accomplishment): bool
    {
        $performanceIndicator = $accomplishment->performanceIndicator;

        return $user->can('accomplishments.delete')
            && $performanceIndicator !== null
            && $user->can('view', $performanceIndicator);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('accomplishments.delete')
            && $user->can('performance-indicators.view');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Accomplishment $accomplishment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Accomplishment $accomplishment): bool
    {
        return false;
    }
}
