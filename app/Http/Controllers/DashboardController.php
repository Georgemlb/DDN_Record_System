<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\FlightPortalDeposit;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with real-time KPIs, chart data, and recent transactions.
     */
    public function index(): Response
    {
        $period = request('period', 'this_month');
        [$startDate, $endDate] = $this->resolveDateRange($period);

        // --- KPI Calculations ---
        $transactionQuery = Transaction::query()
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        $totalSales = (float) (clone $transactionQuery)->sum('amount');
        $totalDdn = (float) (clone $transactionQuery)->sum('ddn_amount');
        $totalCash = (float) (clone $transactionQuery)->where('payment_method', Transaction::PAYMENT_METHOD_CASH)->sum('amount');
        $totalGcash = (float) (clone $transactionQuery)->where('payment_method', Transaction::PAYMENT_METHOD_GCASH)->sum('amount');

        $expenseQuery = Expense::query()
            ->whereBetween('expense_date', [$startDate, $endDate]);

        $totalExpenses = (float) (clone $expenseQuery)->sum('amount');
        $netProfit = round($totalDdn - $totalExpenses, 2);

        // --- Sales Trend (per day) ---
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

        // --- Expenses by Category ---
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

        // --- Recent Transactions ---
        $recentTransactions = Transaction::query()
            ->with(['baggageOption', 'onlineAssistanceType'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $portalBalance = FlightPortalDeposit::getCurrentBalance();
        $portalLowBalance = $portalBalance < FlightPortalDeposit::LOW_BALANCE_THRESHOLD;

        return Inertia::render('Dashboard', [
            'period' => $period,
            'kpis' => [
                'total_sales' => $totalSales,
                'total_ddn' => $totalDdn,
                'total_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
                'total_cash' => $totalCash,
                'total_gcash' => $totalGcash,
            ],
            'portal' => [
                'balance' => $portalBalance,
                'is_low_balance' => $portalLowBalance,
                'low_balance_threshold' => FlightPortalDeposit::LOW_BALANCE_THRESHOLD,
            ],
            'salesTrend' => $salesTrend,
            'expensesByCategory' => $expensesByCategory,
            'recentTransactions' => $recentTransactions,
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
