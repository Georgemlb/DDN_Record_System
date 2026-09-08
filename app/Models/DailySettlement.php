<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $settlement_date
 * @property int|null $user_id
 * @property int|null $closed_by_user_id
 * @property string $status
 * @property string $opening_cash
 * @property string $cash_in
 * @property string $cash_out
 * @property string $expected_cash
 * @property string|null $actual_cash
 * @property string|null $discrepancy
 * @property string $total_gcash
 * @property string $total_bank_transfer
 * @property string $total_sales
 * @property string $total_ddn_earnings
 * @property string|null $notes
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property User|null $user
 * @property User|null $closedByUser
 */
#[Fillable([
    'settlement_date',
    'user_id',
    'closed_by_user_id',
    'status',
    'opening_cash',
    'cash_in',
    'cash_out',
    'expected_cash',
    'actual_cash',
    'discrepancy',
    'total_gcash',
    'total_bank_transfer',
    'total_sales',
    'total_ddn_earnings',
    'notes',
    'opened_at',
    'closed_at',
])]
class DailySettlement extends Model
{
    use SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settlement_date' => 'date:Y-m-d',
            'opening_cash' => 'decimal:2',
            'cash_in' => 'decimal:2',
            'cash_out' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'discrepancy' => 'decimal:2',
            'total_gcash' => 'decimal:2',
            'total_bank_transfer' => 'decimal:2',
            'total_sales' => 'decimal:2',
            'total_ddn_earnings' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * Calculate financial totals for a given date from transactions and expenses.
     *
     * @return array{
     *     cash_in: float,
     *     total_gcash: float,
     *     total_sales: float,
     *     total_ddn_earnings: float,
     *     cash_out: float,
     *     expense_gcash: float,
     *     total_bank_transfer: float,
     *     total_expenses: float
     * }
     */
    public static function calculateMetricsForDate(string|\DateTimeInterface $date): array
    {
        $dateString = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : Carbon::parse((string) $date)->toDateString();

        $transactions = Transaction::query()
            ->whereDate('transaction_date', $dateString)
            ->get(['amount', 'ddn_amount']);

        $totalSales = 0.0;
        $totalDdnEarnings = 0.0;

        foreach ($transactions as $tx) {
            $amount = (float) $tx->amount;
            $ddn = (float) ($tx->ddn_amount ?? $tx->amount);

            $totalSales += $amount;
            $totalDdnEarnings += $ddn;
        }

        // Cash In and GCash In are calculated from payments collected on this date
        $payments = TransactionPayment::query()
            ->whereDate('payment_date', $dateString)
            ->get(['transaction_id', 'payment_method', 'amount', 'cash_amount', 'gcash_amount']);

        $cashIn = 0.0;
        $totalGcash = 0.0;
        $processedTransactionIds = [];

        foreach ($payments as $payment) {
            $amt = (float) $payment->amount;
            if ($payment->transaction_id) {
                $processedTransactionIds[] = $payment->transaction_id;
            }

            if ($payment->payment_method === Transaction::PAYMENT_METHOD_CASH) {
                $cashIn += $amt;
            } elseif ($payment->payment_method === Transaction::PAYMENT_METHOD_GCASH) {
                $totalGcash += $amt;
            } elseif ($payment->payment_method === Transaction::PAYMENT_METHOD_SPLIT) {
                $cashIn += (float) ($payment->cash_amount ?? 0);
                $totalGcash += (float) ($payment->gcash_amount ?? 0);
            }
        }

        // Fallback for transactions on this date that have no payments attached
        $fallbackTransactions = Transaction::query()
            ->whereDate('transaction_date', $dateString)
            ->whereNotIn('id', array_unique($processedTransactionIds))
            ->whereDoesntHave('payments')
            ->get(['payment_method', 'amount', 'cash_amount', 'gcash_amount', 'amount_paid', 'payment_status']);

        foreach ($fallbackTransactions as $tx) {
            $amountToCount = $tx->amount_paid !== null && (float) $tx->amount_paid > 0
                ? (float) $tx->amount_paid
                : ($tx->payment_status === Transaction::PAYMENT_STATUS_UNPAID ? 0.0 : (float) $tx->amount);

            if ($amountToCount <= 0) {
                continue;
            }

            if ($tx->payment_method === Transaction::PAYMENT_METHOD_CASH) {
                $cashIn += $amountToCount;
            } elseif ($tx->payment_method === Transaction::PAYMENT_METHOD_GCASH) {
                $totalGcash += $amountToCount;
            } elseif ($tx->payment_method === Transaction::PAYMENT_METHOD_SPLIT) {
                $cashIn += (float) ($tx->cash_amount ?? 0);
                $totalGcash += (float) ($tx->gcash_amount ?? 0);
            }
        }

        $expenses = Expense::query()
            ->whereDate('expense_date', $dateString)
            ->get(['payment_method', 'amount']);

        $cashOut = 0.0;
        $expenseGcash = 0.0;
        $totalBankTransfer = 0.0;
        $totalExpenses = 0.0;

        foreach ($expenses as $exp) {
            $amount = (float) $exp->amount;
            $totalExpenses += $amount;

            if ($exp->payment_method === Expense::PAYMENT_METHOD_CASH) {
                $cashOut += $amount;
            } elseif ($exp->payment_method === Expense::PAYMENT_METHOD_GCASH) {
                $expenseGcash += $amount;
            } elseif ($exp->payment_method === Expense::PAYMENT_METHOD_BANK_TRANSFER) {
                $totalBankTransfer += $amount;
            }
        }

        // Personal expense reimbursements received on this date
        $reimbursements = Expense::query()
            ->where('is_personal', true)
            ->where('reimbursement_status', Expense::REIMBURSEMENT_STATUS_REIMBURSED)
            ->whereDate('reimbursed_at', $dateString)
            ->get(['amount', 'reimbursement_method']);

        foreach ($reimbursements as $reimb) {
            $amount = (float) $reimb->amount;
            if ($reimb->reimbursement_method === Expense::PAYMENT_METHOD_CASH) {
                $cashIn += $amount;
            } elseif ($reimb->reimbursement_method === Expense::PAYMENT_METHOD_GCASH) {
                $totalGcash += $amount;
            } elseif ($reimb->reimbursement_method === Expense::PAYMENT_METHOD_BANK_TRANSFER) {
                $totalBankTransfer += $amount;
            }
        }

        return [
            'cash_in' => round($cashIn, 2),
            'total_gcash' => round($totalGcash, 2),
            'total_sales' => round($totalSales, 2),
            'total_ddn_earnings' => round($totalDdnEarnings, 2),
            'cash_out' => round($cashOut, 2),
            'expense_gcash' => round($expenseGcash, 2),
            'total_bank_transfer' => round($totalBankTransfer, 2),
            'total_expenses' => round($totalExpenses, 2),
        ];
    }

    /**
     * Recalculate and update current settlement with latest day metrics.
     */
    public function syncMetricsWithRecords(): void
    {
        $dateString = $this->settlement_date instanceof \DateTimeInterface
            ? $this->settlement_date->format('Y-m-d')
            : Carbon::parse((string) $this->settlement_date)->toDateString();

        $metrics = self::calculateMetricsForDate($dateString);

        $this->cash_in = $metrics['cash_in'];
        $this->cash_out = $metrics['cash_out'];
        $this->expected_cash = round((float) $this->opening_cash + $metrics['cash_in'] - $metrics['cash_out'], 2);
        $this->total_gcash = $metrics['total_gcash'];
        $this->total_bank_transfer = $metrics['total_bank_transfer'];
        $this->total_sales = $metrics['total_sales'];
        $this->total_ddn_earnings = $metrics['total_ddn_earnings'];

        if ($this->actual_cash !== null) {
            $this->discrepancy = round((float) $this->actual_cash - (float) $this->expected_cash, 2);
        }
    }

    /**
     * Synchronize daily settlement for a specific date if it exists.
     */
    public static function syncForDate(string $date): void
    {
        $settlement = static::where('settlement_date', $date)->first();
        if ($settlement) {
            $settlement->syncMetricsWithRecords();
            $settlement->save();
        }
    }
}
