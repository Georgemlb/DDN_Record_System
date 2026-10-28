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
 * @property Carbon $entry_date
 * @property int $sort_order
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
    'entry_date',
    'sort_order',
    'notes',
])]
class GcashLedgerEntry extends Model
{
    use SoftDeletes;

    // Inflows into GCash wallet
    public const TYPE_CUSTOMER_PAYMENT = 'customer_payment';

    public const TYPE_REMITTANCE_CASH_OUT = 'remittance_cash_out';

    public const TYPE_EXPENSE_REIMBURSEMENT = 'expense_reimbursement';

    // Outflows from GCash wallet
    public const TYPE_REMITTANCE_CASH_IN = 'remittance_cash_in';

    public const TYPE_GOVERNMENT_DISBURSEMENT = 'government_disbursement';

    public const TYPE_EXPENSE_PAYMENT = 'expense_payment';

    public const TYPE_PORTAL_RELOAD = 'portal_reload';

    // Manual balance adjustment / Anchor
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const LOW_BALANCE_THRESHOLD = 5000.00;

    public const TYPES = [
        self::TYPE_CUSTOMER_PAYMENT,
        self::TYPE_REMITTANCE_CASH_OUT,
        self::TYPE_EXPENSE_REIMBURSEMENT,
        self::TYPE_REMITTANCE_CASH_IN,
        self::TYPE_GOVERNMENT_DISBURSEMENT,
        self::TYPE_EXPENSE_PAYMENT,
        self::TYPE_PORTAL_RELOAD,
        self::TYPE_ADJUSTMENT,
    ];

    public const INFLOW_TYPES = [
        self::TYPE_CUSTOMER_PAYMENT,
        self::TYPE_REMITTANCE_CASH_OUT,
        self::TYPE_EXPENSE_REIMBURSEMENT,
    ];

    public const OUTFLOW_TYPES = [
        self::TYPE_REMITTANCE_CASH_IN,
        self::TYPE_GOVERNMENT_DISBURSEMENT,
        self::TYPE_EXPENSE_PAYMENT,
        self::TYPE_PORTAL_RELOAD,
    ];

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
        static::saving(function (self $entry) {
            if ($entry->entry_date) {
                $entry->attributes['entry_date'] = Carbon::parse($entry->entry_date)->toDateString();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class)->withTrashed();
    }

    /**
     * Get the current live GCash wallet balance.
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
                if (in_array($entry->type, self::INFLOW_TYPES, true)) {
                    $running += $amount;
                } elseif (in_array($entry->type, self::OUTFLOW_TYPES, true)) {
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
     * Synchronize GCash entries linked to a Transaction.
     */
    public static function syncForTransaction(Transaction $transaction, bool $recalculate = true): void
    {
        $date = $transaction->transaction_date;
        $userId = $transaction->user_id ?? auth()->id();

        // Remittance Cash-Out: customer sent GCash to agency (+GCash)
        $isRemittanceCashOut = $transaction->type === Transaction::TYPE_REMITTANCE
            && ($transaction->service_details === 'GCASH_CASH_OUT' || stripos($transaction->notes ?? '', 'cash out') !== false);

        // Remittance Cash-In: agency sent GCash from wallet (-GCash)
        $isRemittanceCashIn = $transaction->type === Transaction::TYPE_REMITTANCE
            && ($transaction->service_details === 'GCASH_CASH_IN' || stripos($transaction->notes ?? '', 'cash in') !== false);

        // 1. Manage Payment Inflow Entries
        // When transaction has recorded TransactionPayment records, sync each GCash payment
        $payments = $transaction->payments()->get();

        if ($isRemittanceCashOut) {
            // Remittance Cash-Out: Single inflow for remittance amount
            $paymentEntry = static::withTrashed()
                ->where('transaction_id', $transaction->id)
                ->where('type', self::TYPE_REMITTANCE_CASH_OUT)
                ->first();

            if (! $paymentEntry) {
                $paymentEntry = new static;
                $paymentEntry->transaction_id = $transaction->id;
                $maxOrder = static::whereDate('entry_date', $date)->max('sort_order') ?? 0;
                $paymentEntry->sort_order = $maxOrder + 1;
            } elseif ($paymentEntry->trashed()) {
                $paymentEntry->restore();
            }

            $paymentEntry->user_id = $userId;
            $paymentEntry->type = self::TYPE_REMITTANCE_CASH_OUT;
            $paymentEntry->amount = (float) $transaction->amount;
            $paymentEntry->entry_date = $date;
            $paymentEntry->reference_number = $transaction->reference_number;
            $paymentEntry->notes = "GCash Cash-Out from {$transaction->customer_name}";
            $paymentEntry->save();

            // Clean up any normal customer payment entries if any existed
            static::where('transaction_id', $transaction->id)
                ->where('type', self::TYPE_CUSTOMER_PAYMENT)
                ->delete();
        } elseif ($payments->isNotEmpty()) {
            // Remove any remittance cash out entries if type changed
            static::where('transaction_id', $transaction->id)
                ->where('type', self::TYPE_REMITTANCE_CASH_OUT)
                ->delete();

            $existingEntries = static::withTrashed()
                ->where('transaction_id', $transaction->id)
                ->where('type', self::TYPE_CUSTOMER_PAYMENT)
                ->orderBy('id')
                ->get();

            $gcashPayments = $payments->filter(function (TransactionPayment $p) {
                if ($p->payment_method === Transaction::PAYMENT_METHOD_GCASH) {
                    return true;
                }
                if ($p->payment_method === Transaction::PAYMENT_METHOD_SPLIT && (float) ($p->gcash_amount ?? 0) > 0) {
                    return true;
                }

                return false;
            })->values();

            // Match each GCash payment with an entry by index
            foreach ($gcashPayments as $index => $payment) {
                $paymentInflow = $payment->gcash_tendered !== null && (float) $payment->gcash_tendered > 0
                    ? (float) $payment->gcash_tendered
                    : ($payment->payment_method === Transaction::PAYMENT_METHOD_SPLIT
                        ? (float) ($payment->gcash_amount ?? 0)
                        : (float) $payment->amount);

                if ($paymentInflow <= 0) {
                    continue;
                }

                $paymentDate = $payment->payment_date ?: $date;
                $paymentUser = $payment->user_id ?? $userId;

                /** @var GcashLedgerEntry|null $entry */
                $entry = $existingEntries->get($index);

                if (! $entry) {
                    $entry = new static;
                    $entry->transaction_id = $transaction->id;
                    $maxOrder = static::whereDate('entry_date', $paymentDate)->max('sort_order') ?? 0;
                    $entry->sort_order = $maxOrder + 1;
                } elseif ($entry->trashed()) {
                    $entry->restore();
                }

                $entry->user_id = $paymentUser;
                $entry->type = self::TYPE_CUSTOMER_PAYMENT;
                $entry->amount = $paymentInflow;
                $entry->entry_date = $paymentDate;
                $entry->reference_number = $transaction->reference_number;

                $changeText = (float) ($payment->cash_change ?? 0) > 0
                    ? ' (₱'.number_format((float) $payment->cash_change, 2).' cash change)'
                    : '';
                $noteSuffix = $payment->notes ? " - {$payment->notes}" : ($transaction->service_details ? " ({$transaction->service_details})" : '');

                $entry->notes = "Payment from {$transaction->customer_name}{$changeText}{$noteSuffix}";
                $entry->save();
            }

            // Soft-delete extra existing entries if fewer GCash payments exist now
            if ($existingEntries->count() > $gcashPayments->count()) {
                for ($i = $gcashPayments->count(); $i < $existingEntries->count(); $i++) {
                    $existingEntries[$i]->delete();
                }
            }
        } else {
            // Fallback for transactions without TransactionPayment records
            $gcashCollected = 0.00;
            if ($transaction->payment_method === Transaction::PAYMENT_METHOD_GCASH) {
                $gcashCollected = (float) ($transaction->gcash_tendered ?: ($transaction->amount_paid ?: $transaction->amount));
            } elseif ($transaction->payment_method === Transaction::PAYMENT_METHOD_SPLIT) {
                $gcashCollected = (float) ($transaction->gcash_tendered ?: ($transaction->gcash_amount ?? 0));
            }

            $paymentEntry = static::withTrashed()
                ->where('transaction_id', $transaction->id)
                ->where('type', self::TYPE_CUSTOMER_PAYMENT)
                ->first();

            if ($gcashCollected > 0) {
                if (! $paymentEntry) {
                    $paymentEntry = new static;
                    $paymentEntry->transaction_id = $transaction->id;
                    $maxOrder = static::whereDate('entry_date', $date)->max('sort_order') ?? 0;
                    $paymentEntry->sort_order = $maxOrder + 1;
                } elseif ($paymentEntry->trashed()) {
                    $paymentEntry->restore();
                }

                $paymentEntry->user_id = $userId;
                $paymentEntry->type = self::TYPE_CUSTOMER_PAYMENT;
                $paymentEntry->amount = $gcashCollected;
                $paymentEntry->entry_date = $date;
                $paymentEntry->reference_number = $transaction->reference_number;
                $paymentEntry->notes = (float) ($transaction->cash_change ?? 0) > 0
                    ? "Payment from {$transaction->customer_name} (₱".number_format((float) $transaction->cash_change, 2).' cash change)'
                    : "Payment from {$transaction->customer_name} ({$transaction->service_details})";
                $paymentEntry->save();
            } else {
                if ($paymentEntry) {
                    $paymentEntry->delete();
                }
            }

            // Clean up any extra customer payment entries
            static::where('transaction_id', $transaction->id)
                ->where('type', self::TYPE_CUSTOMER_PAYMENT)
                ->when($paymentEntry, fn ($q) => $q->where('id', '!=', $paymentEntry->id))
                ->delete();
        }

        // 2. Check for Government / Third-Party Disbursement Outflow via GCash
        $disbursementEntry = static::withTrashed()
            ->where('transaction_id', $transaction->id)
            ->where('type', self::TYPE_GOVERNMENT_DISBURSEMENT)
            ->first();

        $thirdPartyCost = (float) ($transaction->third_party_payment ?? 0);
        $assistanceType = $transaction->onlineAssistanceType;
        $hasDisbursement = $transaction->type !== Transaction::TYPE_ONLINE_ASSISTANCE
            || ($assistanceType ? (bool) $assistanceType->has_disbursement : true);

        $isDisbursedViaGcash = $hasDisbursement && (
            $transaction->disbursement_source === Transaction::DISBURSEMENT_SOURCE_GCASH
            || ($transaction->type === Transaction::TYPE_ONLINE_ASSISTANCE && $transaction->disbursement_source !== Transaction::DISBURSEMENT_SOURCE_CASH && $transaction->disbursement_source !== Transaction::DISBURSEMENT_SOURCE_NONE)
        );

        if ($thirdPartyCost > 0 && $isDisbursedViaGcash && ! $isRemittanceCashIn && ! $isRemittanceCashOut) {
            if (! $disbursementEntry) {
                $disbursementEntry = new static;
                $disbursementEntry->transaction_id = $transaction->id;
                $maxOrder = static::whereDate('entry_date', $date)->max('sort_order') ?? 0;
                $disbursementEntry->sort_order = $maxOrder + 1;
            } elseif ($disbursementEntry->trashed()) {
                $disbursementEntry->restore();
            }

            $disbursementEntry->user_id = $userId;
            $disbursementEntry->type = self::TYPE_GOVERNMENT_DISBURSEMENT;
            $disbursementEntry->amount = $thirdPartyCost;
            $disbursementEntry->entry_date = $date;
            $disbursementEntry->reference_number = $transaction->reference_number;
            $disbursementEntry->notes = $transaction->type === Transaction::TYPE_TRANSLATION
                ? "Translator fee disbursement for {$transaction->customer_name}"
                : "Government fee disbursement for {$transaction->customer_name}".($transaction->service_details ? " ({$transaction->service_details})" : '');
            $disbursementEntry->save();
        } else {
            if ($disbursementEntry) {
                $disbursementEntry->delete();
            }
        }

        // 3. Remittance Cash-In Outflow (agency sent GCash to customer)
        $cashInEntry = static::withTrashed()
            ->where('transaction_id', $transaction->id)
            ->where('type', self::TYPE_REMITTANCE_CASH_IN)
            ->first();

        if ($isRemittanceCashIn) {
            if (! $cashInEntry) {
                $cashInEntry = new static;
                $cashInEntry->transaction_id = $transaction->id;
                $maxOrder = static::whereDate('entry_date', $date)->max('sort_order') ?? 0;
                $cashInEntry->sort_order = $maxOrder + 1;
            } elseif ($cashInEntry->trashed()) {
                $cashInEntry->restore();
            }

            $cashInEntry->user_id = $userId;
            $cashInEntry->type = self::TYPE_REMITTANCE_CASH_IN;
            $cashInEntry->amount = (float) $transaction->amount;
            $cashInEntry->entry_date = $date;
            $cashInEntry->reference_number = $transaction->reference_number;
            $cashInEntry->notes = "GCash Cash-In transfer to {$transaction->customer_name}";
            $cashInEntry->save();
        } else {
            if ($cashInEntry) {
                $cashInEntry->delete();
            }
        }

        if ($recalculate) {
            static::recalculateRunningBalances();
        }
    }

    /**
     * Soft delete GCash entries linked to a Transaction.
     */
    public static function removeForTransaction(int $transactionId): void
    {
        static::where('transaction_id', $transactionId)->delete();
        static::recalculateRunningBalances();
    }

    /**
     * Restore soft deleted GCash entries linked to a Transaction.
     */
    public static function restoreForTransaction(int $transactionId): void
    {
        static::onlyTrashed()->where('transaction_id', $transactionId)->restore();
        static::recalculateRunningBalances();
    }

    /**
     * Permanently delete GCash entries linked to a Transaction.
     */
    public static function forceRemoveForTransaction(int $transactionId): void
    {
        static::withTrashed()->where('transaction_id', $transactionId)->forceDelete();
        static::recalculateRunningBalances();
    }

    /**
     * Synchronize GCash entries linked to an Expense.
     */
    public static function syncForExpense(Expense $expense, bool $recalculate = true): void
    {
        if ($expense->trashed()) {
            static::where('expense_id', $expense->id)->delete();

            if ($recalculate) {
                static::recalculateRunningBalances();
            }

            return;
        }

        // 1. Adjustment entry (cash drawer top-up into GCash)
        $adjustmentEntry = static::withTrashed()
            ->where('expense_id', $expense->id)
            ->where('type', self::TYPE_ADJUSTMENT)
            ->first();

        if ($adjustmentEntry && $expense->payment_method === Expense::PAYMENT_METHOD_CASH) {
            if ($adjustmentEntry->trashed()) {
                $adjustmentEntry->restore();
            }

            $adjustmentEntry->user_id = $expense->user_id ?? auth()->id();
            $adjustmentEntry->amount = (float) $expense->amount;
            $adjustmentEntry->entry_date = $expense->expense_date;
            $adjustmentEntry->reference_number = $expense->reference_number;
            $adjustmentEntry->save();
        } elseif ($adjustmentEntry && $expense->payment_method !== Expense::PAYMENT_METHOD_CASH) {
            $adjustmentEntry->delete();
        }

        // 2. Expense Outflow entry (paid via GCash)
        $outflowEntry = static::withTrashed()
            ->where('expense_id', $expense->id)
            ->whereIn('type', [self::TYPE_EXPENSE_PAYMENT, self::TYPE_PORTAL_RELOAD])
            ->first();

        if ($expense->payment_method === Expense::PAYMENT_METHOD_GCASH) {
            if (! $outflowEntry) {
                $outflowEntry = new static;
                $outflowEntry->expense_id = $expense->id;
                $maxOrder = static::whereDate('entry_date', $expense->expense_date)->max('sort_order') ?? 0;
                $outflowEntry->sort_order = $maxOrder + 1;
            } elseif ($outflowEntry->trashed()) {
                $outflowEntry->restore();
            }

            $outflowEntry->user_id = $expense->user_id ?? auth()->id();
            $outflowEntry->type = stripos($expense->title ?? '', 'portal') !== false
                ? self::TYPE_PORTAL_RELOAD
                : self::TYPE_EXPENSE_PAYMENT;
            $outflowEntry->amount = (float) $expense->amount;
            $outflowEntry->entry_date = $expense->expense_date;
            $outflowEntry->reference_number = $expense->reference_number;
            $outflowEntry->notes = $expense->title ?: 'Expense paid via GCash';
            $outflowEntry->save();
        } else {
            if ($outflowEntry) {
                $outflowEntry->delete();
            }
        }

        // 3. Personal Expense Reimbursement Inflow entry (reimbursed via GCash)
        $reimbursementEntry = static::withTrashed()
            ->where('expense_id', $expense->id)
            ->where('type', self::TYPE_EXPENSE_REIMBURSEMENT)
            ->first();

        $isReimbursedViaGcash = $expense->is_personal
            && $expense->reimbursement_status === Expense::REIMBURSEMENT_STATUS_REIMBURSED
            && $expense->reimbursement_method === Expense::PAYMENT_METHOD_GCASH;

        if ($isReimbursedViaGcash) {
            $reimbursementDate = $expense->reimbursed_at ?? $expense->expense_date;

            if (! $reimbursementEntry) {
                $reimbursementEntry = new static;
                $reimbursementEntry->expense_id = $expense->id;
                $maxOrder = static::whereDate('entry_date', $reimbursementDate)->max('sort_order') ?? 0;
                $reimbursementEntry->sort_order = $maxOrder + 1;
            } elseif ($reimbursementEntry->trashed()) {
                $reimbursementEntry->restore();
            }

            $reimbursementEntry->user_id = $expense->user_id ?? auth()->id();
            $reimbursementEntry->type = self::TYPE_EXPENSE_REIMBURSEMENT;
            $reimbursementEntry->amount = (float) $expense->amount;
            $reimbursementEntry->entry_date = $reimbursementDate;
            $reimbursementEntry->reference_number = $expense->reference_number;
            $reimbursementEntry->notes = 'Personal expense reimbursement: '.($expense->title ?: 'Personal errand');
            $reimbursementEntry->save();
        } else {
            if ($reimbursementEntry) {
                $reimbursementEntry->delete();
            }
        }

        if ($recalculate) {
            static::recalculateRunningBalances();
        }
    }
}
