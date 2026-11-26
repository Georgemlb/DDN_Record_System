<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Models\TransactionPayment;

class FinancialMetricsService
{
    /**
     * Calculate accurate Cash and GCash collections for a date range via TransactionPayment
     * with fallback for legacy transactions without payment records.
     *
     * @return array{cash: float, gcash: float}
     */
    public function calculateCashAndGcashCollections(string $startDate, string $endDate): array
    {
        $periodPaymentCash = (float) TransactionPayment::query()
            ->whereHas('transaction')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('payment_method', Transaction::PAYMENT_METHOD_CASH)
            ->sum('amount');

        $periodPaymentSplitCash = (float) TransactionPayment::query()
            ->whereHas('transaction')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('payment_method', Transaction::PAYMENT_METHOD_SPLIT)
            ->sum('cash_amount');

        $cashSales = $periodPaymentCash + $periodPaymentSplitCash;

        $periodPaymentGcash = (float) TransactionPayment::query()
            ->whereHas('transaction')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('payment_method', Transaction::PAYMENT_METHOD_GCASH)
            ->sum('amount');

        $periodPaymentSplitGcash = (float) TransactionPayment::query()
            ->whereHas('transaction')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('payment_method', Transaction::PAYMENT_METHOD_SPLIT)
            ->sum('gcash_amount');

        $gcashSales = $periodPaymentGcash + $periodPaymentSplitGcash;

        // Fallback for transactions in range without payments
        $fallbackTransactions = Transaction::query()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->whereDoesntHave('payments')
            ->get(['payment_method', 'amount', 'cash_amount', 'gcash_amount', 'amount_paid', 'payment_status']);

        foreach ($fallbackTransactions as $tx) {
            $amt = $tx->amount_paid !== null && (float) $tx->amount_paid > 0
                ? (float) $tx->amount_paid
                : ($tx->payment_status === Transaction::PAYMENT_STATUS_UNPAID ? 0.0 : (float) $tx->amount);

            if ($tx->payment_method === Transaction::PAYMENT_METHOD_CASH) {
                $cashSales += $amt;
            } elseif ($tx->payment_method === Transaction::PAYMENT_METHOD_GCASH) {
                $gcashSales += $amt;
            } elseif ($tx->payment_method === Transaction::PAYMENT_METHOD_SPLIT) {
                $cashSales += (float) ($tx->cash_amount ?? 0);
                $gcashSales += (float) ($tx->gcash_amount ?? 0);
            }
        }

        return [
            'cash' => round($cashSales, 2),
            'gcash' => round($gcashSales, 2),
        ];
    }

    /**
     * Calculate business expense totals, excluding non-operating capital transfers
     * (Flight Portal Reload & GCash Wallet Top-Up) from operating expenses.
     *
     * @return array{
     *     total_expenses: float,
     *     operating_expenses: float,
     *     portal_reloads: float,
     *     gcash_top_ups: float,
     *     non_operating_expenses: float,
     *     cash_expenses: float,
     *     gcash_expenses: float,
     *     bank_expenses: float,
     *     expense_count: int,
     *     categories_breakdown: array<int, array{category: string, count: int, total: float}>
     * }
     */
    public function calculatePeriodExpenses(string $startDate, string $endDate): array
    {
        $expenses = Expense::query()
            ->with('category:id,name')
            ->business()
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get(['expense_category_id', 'payment_method', 'amount']);

        $totalExpenses = 0.0;
        $portalReloadExpenses = 0.0;
        $gcashTopUpExpenses = 0.0;
        $cashExpenses = 0.0;
        $gcashExpenses = 0.0;
        $bankExpenses = 0.0;
        $categoriesGrouped = [];

        foreach ($expenses as $exp) {
            $amount = (float) $exp->amount;
            $totalExpenses += $amount;

            $catName = $exp->category ? $exp->category->name : 'Uncategorized';
            if ($catName === ExpenseCategory::FLIGHT_PORTAL_RELOAD) {
                $portalReloadExpenses += $amount;
            } elseif ($catName === ExpenseCategory::GCASH_WALLET_TOP_UP) {
                $gcashTopUpExpenses += $amount;
            }

            if ($exp->payment_method === Expense::PAYMENT_METHOD_CASH) {
                $cashExpenses += $amount;
            } elseif ($exp->payment_method === Expense::PAYMENT_METHOD_GCASH) {
                $gcashExpenses += $amount;
            } elseif ($exp->payment_method === Expense::PAYMENT_METHOD_BANK_TRANSFER) {
                $bankExpenses += $amount;
            }

            if (! isset($categoriesGrouped[$catName])) {
                $categoriesGrouped[$catName] = ['category' => $catName, 'count' => 0, 'total' => 0.0];
            }
            $categoriesGrouped[$catName]['count']++;
            $categoriesGrouped[$catName]['total'] += $amount;
        }

        $nonOperating = $portalReloadExpenses + $gcashTopUpExpenses;
        $operatingExpenses = round(max(0, $totalExpenses - $nonOperating), 2);

        usort($categoriesGrouped, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'total_expenses' => round($totalExpenses, 2),
            'operating_expenses' => $operatingExpenses,
            'portal_reloads' => round($portalReloadExpenses, 2),
            'gcash_top_ups' => round($gcashTopUpExpenses, 2),
            'non_operating_expenses' => round($nonOperating, 2),
            'cash_expenses' => round($cashExpenses, 2),
            'gcash_expenses' => round($gcashExpenses, 2),
            'bank_expenses' => round($bankExpenses, 2),
            'expense_count' => $expenses->count(),
            'categories_breakdown' => $categoriesGrouped,
        ];
    }
}
