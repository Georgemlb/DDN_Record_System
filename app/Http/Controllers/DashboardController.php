<?php

namespace App\Http\Controllers;

use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\FlightPortalDeposit;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with real-time KPIs, chart data, and recent transactions/expenses.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $isStaff = (bool) $user?->isStaff();

        $todayDate = now()->toDateString();
        $todayMetrics = DailySettlement::calculateMetricsForDate($todayDate);

        $todaySettlement = DailySettlement::query()
            ->whereDate('settlement_date', $todayDate)
            ->first();

        $openingCash = $todaySettlement ? (float) $todaySettlement->opening_cash : 0.0;
        $cashInBox = round($openingCash + $todayMetrics['cash_in'] - $todayMetrics['cash_out'], 2);
        $exactNetGcash = round($todayMetrics['total_gcash'] - $todayMetrics['expense_gcash'], 2);

        $registerData = [
            'opening_cash' => $openingCash,
            'cash_in' => $todayMetrics['cash_in'],
            'cash_out' => $todayMetrics['cash_out'],
            'cash_in_box' => $cashInBox,
            'gcash_in' => $todayMetrics['total_gcash'],
            'gcash_out' => $todayMetrics['expense_gcash'],
            'exact_net_gcash' => $exactNetGcash,
            'total_sales' => $todayMetrics['total_sales'],
            'total_expenses' => $todayMetrics['total_expenses'],
        ];

        // Recent Transactions (latest 5)
        $recentTransactions = Transaction::query()
            ->with(['baggageOption', 'onlineAssistanceType', 'customer'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // Recent Expenses (latest 5)
        $recentExpenses = Expense::query()
            ->with('category')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        if ($isStaff) {
            return Inertia::render('Dashboard', [
                'isStaff' => true,
                'period' => 'today',
                'register' => $registerData,
                'recentTransactions' => $recentTransactions,
                'recentExpenses' => $recentExpenses,
            ]);
        }

        // --- Admin / Tech Admin Data ---
        $period = request('period', 'this_month');
        [$startDate, $endDate] = $this->resolveDateRange($period);

        $transactionQuery = Transaction::query()
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        $totalSales = (float) (clone $transactionQuery)->sum('amount');
        $totalDdn = (float) (clone $transactionQuery)->sum('ddn_amount');

        // Accurate Cash & GCash collections for the period via TransactionPayment
        $periodPaymentCash = (float) TransactionPayment::query()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('payment_method', Transaction::PAYMENT_METHOD_CASH)
            ->sum('amount');
        $periodPaymentSplitCash = (float) TransactionPayment::query()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('payment_method', Transaction::PAYMENT_METHOD_SPLIT)
            ->sum('cash_amount');
        $totalPeriodCash = $periodPaymentCash + $periodPaymentSplitCash;

        $periodPaymentGcash = (float) TransactionPayment::query()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('payment_method', Transaction::PAYMENT_METHOD_GCASH)
            ->sum('amount');
        $periodPaymentSplitGcash = (float) TransactionPayment::query()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('payment_method', Transaction::PAYMENT_METHOD_SPLIT)
            ->sum('gcash_amount');
        $totalPeriodGcash = $periodPaymentGcash + $periodPaymentSplitGcash;

        // Fallback for transactions in range without payments
        $fallbackTransactions = (clone $transactionQuery)
            ->whereDoesntHave('payments')
            ->get(['payment_method', 'amount', 'cash_amount', 'gcash_amount', 'amount_paid', 'payment_status']);

        foreach ($fallbackTransactions as $tx) {
            $amt = $tx->amount_paid !== null && (float) $tx->amount_paid > 0
                ? (float) $tx->amount_paid
                : ($tx->payment_status === Transaction::PAYMENT_STATUS_UNPAID ? 0.0 : (float) $tx->amount);

            if ($tx->payment_method === Transaction::PAYMENT_METHOD_CASH) {
                $totalPeriodCash += $amt;
            } elseif ($tx->payment_method === Transaction::PAYMENT_METHOD_GCASH) {
                $totalPeriodGcash += $amt;
            } elseif ($tx->payment_method === Transaction::PAYMENT_METHOD_SPLIT) {
                $totalPeriodCash += (float) ($tx->cash_amount ?? 0);
                $totalPeriodGcash += (float) ($tx->gcash_amount ?? 0);
            }
        }

        $expenseQuery = Expense::query()
            ->whereBetween('expense_date', [$startDate, $endDate]);

        $totalExpenses = (float) (clone $expenseQuery)->sum('amount');
        $netProfit = round($totalDdn - $totalExpenses, 2);

        // Sales Trend (per day)
        $salesTrend = (clone $transactionQuery)
            ->selectRaw('DATE(transaction_date) as date, SUM(amount) as total_sales, SUM(ddn_amount) as total_ddn')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'total_sales' => (float) $row->total_sales,
                'total_ddn' => (float) $row->total_ddn,
            ]);

        // Expenses by Category
        $expensesByCategory = (clone $expenseQuery)
            ->with('category')
            ->selectRaw('expense_category_id, SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category?->name ?? 'Uncategorized',
                'total' => (float) $row->total,
            ]);

        $portalBalance = FlightPortalDeposit::getCurrentBalance();
        $portalLowBalance = $portalBalance < FlightPortalDeposit::LOW_BALANCE_THRESHOLD;

        return Inertia::render('Dashboard', [
            'isStaff' => false,
            'period' => $period,
            'register' => $registerData,
            'kpis' => [
                'total_sales' => $totalSales,
                'total_ddn' => $totalDdn,
                'total_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
                'total_cash' => round($totalPeriodCash, 2),
                'total_gcash' => round($totalPeriodGcash, 2),
                'cash_in_box' => $cashInBox,
                'exact_net_gcash' => $exactNetGcash,
            ],
            'portal' => [
                'balance' => $portalBalance,
                'is_low_balance' => $portalLowBalance,
                'low_balance_threshold' => FlightPortalDeposit::LOW_BALANCE_THRESHOLD,
            ],
            'salesTrend' => $salesTrend,
            'expensesByCategory' => $expensesByCategory,
            'recentTransactions' => $recentTransactions,
            'recentExpenses' => $recentExpenses,
        ]);
    }

    /**
     * Resolve a named period into a [startDate, endDate] tuple (Y-m-d strings).
     *
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [
                $now->toDateString(),
                $now->toDateString(),
            ],
            'this_week' => [
                $now->copy()->startOfWeek()->toDateString(),
                $now->copy()->endOfWeek()->toDateString(),
            ],
            'last_month' => [
                $now->copy()->startOfMonth()->subMonth()->toDateString(),
                $now->copy()->startOfMonth()->subMonth()->endOfMonth()->toDateString(),
            ],
            'this_year' => [
                $now->copy()->startOfYear()->toDateString(),
                $now->copy()->endOfYear()->toDateString(),
            ],
            default => [ // 'this_month'
                $now->copy()->startOfMonth()->toDateString(),
                $now->copy()->endOfMonth()->toDateString(),
            ],
        };
    }
}
