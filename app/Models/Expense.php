<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property int $expense_category_id
 * @property float|int|string $amount
 * @property Carbon|null $expense_date
 * @property string $payment_method
 * @property string|null $payee
 * @property string|null $reference_number
 * @property string $recurring_frequency
 * @property string|null $notes
 * @property bool $is_personal
 * @property string $reimbursement_status
 * @property Carbon|null $reimbursed_at
 * @property string|null $reimbursement_method
 * @property string|null $reimbursement_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property ExpenseCategory|null $category
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
    'is_personal',
    'reimbursement_status',
    'reimbursed_at',
    'reimbursement_method',
    'reimbursement_notes',
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
                $expense->user_id = (int) auth()->id();
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

    public const REIMBURSEMENT_STATUS_NONE = 'none';

    public const REIMBURSEMENT_STATUS_PENDING = 'pending';

    public const REIMBURSEMENT_STATUS_REIMBURSED = 'reimbursed';

    public const REIMBURSEMENT_STATUSES = [
        self::REIMBURSEMENT_STATUS_NONE,
        self::REIMBURSEMENT_STATUS_PENDING,
        self::REIMBURSEMENT_STATUS_REIMBURSED,
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
            'is_personal' => 'boolean',
            'reimbursed_at' => 'date:Y-m-d',
        ];
    }

    /**
     * @param  Builder<Expense>  $query
     * @return Builder<Expense>
     */
    public function scopePersonal(Builder $query): Builder
    {
        return $query->where('is_personal', true);
    }

    /**
     * @param  Builder<Expense>  $query
     * @return Builder<Expense>
     */
    public function scopeBusiness(Builder $query): Builder
    {
        return $query->where('is_personal', false);
    }

    /**
     * @param  Builder<Expense>  $query
     * @return Builder<Expense>
     */
    public function scopePendingReimbursement(Builder $query): Builder
    {
        return $query->where('is_personal', true)
            ->where('reimbursement_status', self::REIMBURSEMENT_STATUS_PENDING);
    }

    /**
     * @param  Builder<Expense>  $query
     * @return Builder<Expense>
     */
    public function scopeReimbursed(Builder $query): Builder
    {
        return $query->where('is_personal', true)
            ->where('reimbursement_status', self::REIMBURSEMENT_STATUS_REIMBURSED);
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
