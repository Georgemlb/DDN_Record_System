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
 * @property string $type
 * @property string $amount
 * @property string $running_balance
 * @property bool $is_balance_anchor
 * @property int|null $transaction_id
 * @property int|null $expense_id
 * @property string|null $reference_number
 * @property string|null $payment_source
 * @property Carbon $entry_date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property User|null $user
 * @property Transaction|null $transaction
 * @property Expense|null $expense
 */
#[Fillable([
    'user_id',
    'type',
    'amount',
    'running_balance',
    'is_balance_anchor',
    'transaction_id',
    'expense_id',
    'reference_number',
    'payment_source',
    'entry_date',
    'sort_order',
    'notes',
])]
class FlightPortalDeposit extends Model
{
    use SoftDeletes;

    public const TYPE_TOP_UP = 'top_up';

    public const TYPE_TICKET_DEDUCTION = 'ticket_deduction';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPES = [
        self::TYPE_TOP_UP,
        self::TYPE_TICKET_DEDUCTION,
        self::TYPE_ADJUSTMENT,
    ];

    public const PAYMENT_SOURCE_CASH = 'cash';

    public const PAYMENT_SOURCE_GCASH = 'gcash';

    public const PAYMENT_SOURCE_BANK_TRANSFER = 'bank_transfer';

    public const PAYMENT_SOURCES = [
        self::PAYMENT_SOURCE_CASH,
        self::PAYMENT_SOURCE_GCASH,
        self::PAYMENT_SOURCE_BANK_TRANSFER,
    ];

    public const LOW_BALANCE_THRESHOLD = 10000.00;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
            'is_balance_anchor' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::saving(function (self $deposit) {
            if ($deposit->entry_date) {
                $deposit->attributes['entry_date'] = \Carbon\Carbon::parse($deposit->entry_date)->toDateString();
            }
        });
    }

    /**
     * User who created the deposit entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Linked transaction (for ticket deductions).
     *
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class)->withTrashed();
    }

    /**
     * Linked expense (if top-up was funded from agency cash/bank).
     *
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class)->withTrashed();
    }

    /**
     * Get the current live deposit balance.
     */
    public static function getCurrentBalance(): float
    {
        $latest = static::orderBy('entry_date', 'desc')
            ->orderBy('sort_order', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $latest ? (float) $latest->running_balance : 0.00;
    }

    /**
     * Recalculate running balances for all entries in chronological order.
     */
    public static function recalculateRunningBalances(): void
    {
        $entries = static::orderBy('entry_date', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $running = 0.00;

        foreach ($entries as $entry) {
            $amount = (float) $entry->amount;

            if ($entry->is_balance_anchor) {
                $running = (float) $entry->running_balance;
            } else {
                if ($entry->type === self::TYPE_TOP_UP) {
                    $running += $amount;
                } elseif ($entry->type === self::TYPE_TICKET_DEDUCTION) {
                    $running -= $amount;
                } elseif ($entry->type === self::TYPE_ADJUSTMENT) {
                    $running += $amount;
                }

                if ((float) $entry->running_balance !== $running) {
                    $entry->running_balance = number_format($running, 2, '.', '');
                    $entry->saveQuietly();
                }
            }
        }
    }

    /**
     * Synchronize a ticket deduction entry when an airline transaction is saved/updated.
     */
    public static function syncTransactionTicketDeduction(Transaction $transaction): ?self
    {
        $cost = (float) ($transaction->third_party_payment ?? 0);

        if (in_array($transaction->type, [Transaction::TYPE_AIRLINE, Transaction::TYPE_FERRY], true) && $cost > 0) {
            $entry = static::withTrashed()->where('transaction_id', $transaction->id)->first();

            if (! $entry) {
                $entry = new static;
                $entry->transaction_id = $transaction->id;
                $entry->type = self::TYPE_TICKET_DEDUCTION;

                $maxOrder = static::whereDate('entry_date', $transaction->transaction_date)->max('sort_order') ?? 0;
                $entry->sort_order = $maxOrder + 1;
            } else {
                if ($entry->trashed()) {
                    $entry->restore();
                }
            }

            $entry->user_id = $transaction->user_id ?? auth()->id();
            $entry->amount = $cost;
            $entry->entry_date = $transaction->transaction_date;
            $entry->reference_number = $transaction->reference_number;
            $entry->notes = "Ticket booking deduction for {$transaction->customer_name}";

            if (! $entry->sort_order) {
                $maxOrder = static::whereDate('entry_date', $transaction->transaction_date)->max('sort_order') ?? 0;
                $entry->sort_order = $maxOrder + 1;
            }

            $entry->save();

            static::recalculateRunningBalances();

            return $entry;
        }

        // If transaction is not airline or cost is 0, remove deduction if it exists
        static::removeTransactionTicketDeduction($transaction->id);

        return null;
    }

    /**
     * Remove or soft delete a transaction's ticket deduction.
     */
    public static function removeTransactionTicketDeduction(int $transactionId): void
    {
        $entry = static::where('transaction_id', $transactionId)->first();

        if ($entry) {
            $entry->delete();
            static::recalculateRunningBalances();
        }
    }

    /**
     * Restore a transaction's ticket deduction.
     */
    public static function restoreTransactionTicketDeduction(int $transactionId): void
    {
        $entry = static::onlyTrashed()->where('transaction_id', $transactionId)->first();

        if ($entry) {
            $entry->restore();
            static::recalculateRunningBalances();
        }
    }
}
