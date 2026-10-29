<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Determine whether the user can view any transactions.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the transaction.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create transactions.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the transaction.
     * Admin/TechAdmin can update any transaction.
     * Staff can only update if they created it AND it was created today.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $transaction->user_id === $user->id
                && $transaction->created_at?->isToday() === true;
        }

        return false;
    }

    /**
     * Determine whether the user can record payment collections for the transaction.
     */
    public function collectPayment(User $user, Transaction $transaction): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * Determine whether the user can delete (soft delete) the transaction.
     * Admin/TechAdmin can delete any transaction.
     * Staff can only delete if they created it AND it was created today.
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $transaction->user_id === $user->id
                && $transaction->created_at?->isToday() === true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the transaction.
     */
    public function restore(User $user, Transaction $transaction): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the transaction.
     */
    public function forceDelete(User $user, Transaction $transaction): bool
    {
        return $user->isAdmin();
    }
}
