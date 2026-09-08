<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Determine whether the user can view any expenses.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the expense.
     */
    public function view(User $user, Expense $expense): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create expenses.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the expense.
     * Admin/TechAdmin can update any expense.
     * Staff can only update if they created it AND it was created today.
     */
    public function update(User $user, Expense $expense): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $expense->user_id === $user->id
                && $expense->created_at?->isToday() === true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete (soft delete) the expense.
     * Admin/TechAdmin can delete any expense.
     * Staff can only delete if they created it AND it was created today.
     */
    public function delete(User $user, Expense $expense): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $expense->user_id === $user->id
                && $expense->created_at?->isToday() === true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the expense.
     */
    public function restore(User $user, Expense $expense): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the expense.
     */
    public function forceDelete(User $user, Expense $expense): bool
    {
        return $user->isAdmin();
    }
}
