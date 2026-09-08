<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $contact_number
 * @property string|null $email
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'name',
    'contact_number',
    'email',
    'notes',
])]
class Customer extends Model
{
    use SoftDeletes;

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope a query to search customers by name or contact number.
     *
     * @param  Builder<Customer>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('contact_number', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }

    /**
     * Scope a query to include transaction counts, total spent, and outstanding balance.
     *
     * @param  Builder<Customer>  $query
     */
    public function scopeWithAggregates(Builder $query): void
    {
        $query->withCount('transactions')
            ->withSum('transactions as total_spent', 'amount_paid')
            ->withSum('transactions as outstanding_balance', 'balance');
    }
}
