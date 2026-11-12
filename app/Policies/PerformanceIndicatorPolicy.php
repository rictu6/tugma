<?php

namespace App\Policies;

use App\Models\PerformanceIndicator;
use App\Models\User;

class PerformanceIndicatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('performance-indicators.view');
    }

    public function view(User $user, PerformanceIndicator $performanceIndicator): bool
    {
        return $user->can('performance-indicators.view');
    }

    public function create(User $user): bool
    {
        return $user->can('performance-indicators.create');
    }

    public function update(User $user, PerformanceIndicator $performanceIndicator): bool
    {
        return $user->can('performance-indicators.update');
    }

    public function delete(User $user, PerformanceIndicator $performanceIndicator): bool
    {
        return $user->can('performance-indicators.delete')
            && ! $performanceIndicator->allotments()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('performance-indicators.delete');
    }
}
