<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('allotments.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can('allotments.view')
            && $user->can('view', $expense->allotment);
    }

    public function create(User $user): bool
    {
        return $user->can('allotments.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->can('allotments.update')
            && $user->can('update', $expense->allotment);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
