<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $transaction_id
 * @property int|null $user_id
 * @property float|int|string $amount
 * @property string $payment_method
 * @property float|int|string|null $cash_amount
 * @property float|int|string|null $gcash_amount
 * @property float|int|string|null $gcash_tendered
 * @property float|int|string|null $cash_change
 * @property float|int|string|null $change_fee
 * @property Carbon $payment_date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Transaction|null $transaction
 * @property User|null $user
 */
#[Fillable([
    'transaction_id',
    'user_id',
    'amount',
    'payment_method',
    'cash_amount',
    'gcash_amount',
    'gcash_tendered',
    'cash_change',
    'change_fee',
    'payment_date',
    'notes',
])]
class TransactionPayment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'cash_amount' => 'decimal:2',
            'gcash_amount' => 'decimal:2',
            'gcash_tendered' => 'decimal:2',
            'cash_change' => 'decimal:2',
            'change_fee' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
