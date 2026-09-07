<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property int $expense_category_id
 * @property string $amount
 * @property Carbon $expense_date
 * @property string $payment_method
 * @property string|null $payee
 * @property string|null $reference_number
 * @property string $recurring_frequency
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property ExpenseCategory $category
 * @property User|null $user
 */
#[Fillable([
    'user_id',
    'title',
    'expense_category_id',
    'amount',
    'expense_date',
    'payment_method',
    'payee',
    'reference_number',
    'recurring_frequency',
    'notes',
])]
class Expense extends Model
{
    use SoftDeletes;

    /**
     * Boot model events to assign user tracking.
     */
    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            if (empty($expense->user_id) && auth()->check()) {
                $expense->user_id = auth()->id();
            }
        });
    }

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_GCASH = 'gcash';

    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';

    public const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_CASH,
        self::PAYMENT_METHOD_GCASH,
        self::PAYMENT_METHOD_BANK_TRANSFER,
    ];

    public const FREQUENCY_NONE = 'none';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_ANNUALLY = 'annually';

    public const RECURRING_FREQUENCIES = [
        self::FREQUENCY_NONE,
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
        self::FREQUENCY_ANNUALLY,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expense_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
