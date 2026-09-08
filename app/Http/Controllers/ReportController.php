<?php

namespace App\Http\Controllers;

use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display the Reports Hub with Daily Cash Settlements and Financial Reports.
     */
    public function index(Request $request): Response
    {
        $currentTab = $request->query('tab', 'daily-settlements');
        $todayDate = now()->toDateString();

        // 1. Daily Settlement data
        $todaySettlement = DailySettlement::query()
            ->with(['user', 'closedByUser'])
            ->whereDate('settlement_date', $todayDate)
            ->first();

        if ($todaySettlement && $todaySettlement->status === DailySettlement::STATUS_OPEN) {
            $todaySettlement->syncMetricsWithRecords();
            $todaySettlement->save();
        }

        $todayDraftMetrics = $todaySettlement ? null : DailySettlement::calculateMetricsForDate($todayDate);

        $suggestedOpeningFloat = (float) (DailySettlement::query()
            ->where('status', DailySettlement::STATUS_CLOSED)
            ->orderByDesc('settlement_date')
            ->value('actual_cash') ?? 0);

        $settlementHistory = DailySettlement::query()
            ->with(['user', 'closedByUser'])
            ->orderByDesc('settlement_date')
            ->paginate(15)
            ->withQueryString();

        // 2. Financial Reports data
        $period = $request->query('period', 'this_month');
        [$startDate, $endDate] = $this->resolveDateRange($period, $request);

        $financialSummary = $this->calculateFinancialReport($startDate, $endDate);

        return Inertia::render('reports/Index', [
            'currentTab' => $currentTab,
            'dailySettlements' => [
                'todaySettlement' => $todaySettlement,
                'todayDraftMetrics' => $todayDraftMetrics,
                'suggestedOpeningFloat' => $suggestedOpeningFloat,
                'history' => $settlementHistory,
                'todayDate' => $todayDate,
            ],
            'financialReports' => [
                'period' => $period,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'summary' => $financialSummary['summary'],
                'servicesBreakdown' => $financialSummary['servicesBreakdown'],
                'expensesBreakdown' => $financialSummary['expensesBreakdown'],
            ],
        ]);
    }

    /**
     * Open a new daily cash settlement drawer.
     */
    public function startDay(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'settlement_date' => ['nullable', 'date'],
        ]);

        $date = $validated['settlement_date'] ?? now()->toDateString();

        $existing = DailySettlement::query()->whereDate('settlement_date', $date)->first();
        if ($existing) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'A settlement for this date already exists.']);

            return back();
        }

        $openingCash = (float) $validated['opening_cash'];
        $metrics = DailySettlement::calculateMetricsForDate($date);

        $settlement = DailySettlement::query()->create([
            'settlement_date' => $date,
            'user_id' => auth()->id(),
            'status' => DailySettlement::STATUS_OPEN,
            'opening_cash' => $openingCash,
            'cash_in' => $metrics['cash_in'],
            'cash_out' => $metrics['cash_out'],
            'expected_cash' => round($openingCash + $metrics['cash_in'] - $metrics['cash_out'], 2),
            'total_gcash' => $metrics['total_gcash'],
            'total_bank_transfer' => $metrics['total_bank_transfer'],
            'total_sales' => $metrics['total_sales'],
            'total_ddn_earnings' => $metrics['total_ddn_earnings'],
            'opened_at' => now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Daily cash register opened with ₱'.number_format($openingCash, 2).' float.',
        ]);

        return back();
    }

    /**
     * Update an existing daily cash settlement record.
     */
    public function update(Request $request, DailySettlement $settlement): RedirectResponse
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'actual_cash' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:'.DailySettlement::STATUS_OPEN.','.DailySettlement::STATUS_CLOSED],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sync_metrics' => ['nullable', 'boolean'],
        ]);

        $settlement->opening_cash = (float) $validated['opening_cash'];
        $settlement->notes = $validated['notes'] ?? null;
        $newStatus = $validated['status'];

        if ($request->boolean('sync_metrics', true)) {
            $settlement->syncMetricsWithRecords();
        } else {
            $settlement->expected_cash = round((float) $settlement->opening_cash + (float) $settlement->cash_in - (float) $settlement->cash_out, 2);
        }

        if ($newStatus === DailySettlement::STATUS_CLOSED) {
            $actualCash = $validated['actual_cash'] !== null
                ? (float) $validated['actual_cash']
                : (float) ($settlement->actual_cash ?? $settlement->expected_cash);

            $settlement->actual_cash = $actualCash;
            $settlement->discrepancy = round($actualCash - (float) $settlement->expected_cash, 2);
            $settlement->status = DailySettlement::STATUS_CLOSED;
            if (! $settlement->closed_at) {
                $settlement->closed_at = now();
                $settlement->closed_by_user_id = auth()->id();
            }
        } else {
            $settlement->status = DailySettlement::STATUS_OPEN;
            $settlement->closed_at = null;
            $settlement->closed_by_user_id = null;
            if ($validated['actual_cash'] !== null) {
                $settlement->actual_cash = (float) $validated['actual_cash'];
                $settlement->discrepancy = round((float) $settlement->actual_cash - (float) $settlement->expected_cash, 2);
            }
        }

        $settlement->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Daily settlement for '.$settlement->settlement_date->format('M d, Y').' updated successfully.',
        ]);

        return back();
    }

    /**
     * Close the daily settlement drawer and calculate discrepancy.
     */
    public function closeDay(Request $request, DailySettlement $settlement): RedirectResponse
    {
        $validated = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $actualCash = (float) $validated['actual_cash'];
        $settlement->actual_cash = $actualCash;
        $settlement->syncMetricsWithRecords();

        $settlement->status = DailySettlement::STATUS_CLOSED;
        $settlement->closed_by_user_id = auth()->id();
        $settlement->closed_at = now();
        if (array_key_exists('notes', $validated)) {
            $settlement->notes = $validated['notes'];
        }
        $settlement->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Daily register for '.$settlement->settlement_date->format('M d, Y').' closed and reconciled.',
        ]);

        return back();
    }

    /**
     * Reopen a closed settlement for corrections.
     */
    public function reopen(DailySettlement $settlement): RedirectResponse
    {
        $settlement->status = DailySettlement::STATUS_OPEN;
        $settlement->closed_by_user_id = null;
        $settlement->closed_at = null;
        $settlement->syncMetricsWithRecords();
        $settlement->save();

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => 'Daily register reopened for adjustments.',
        ]);

        return back();
    }

    /**
     * Retrieve slip data for viewing and printing.
     */
    public function showSlip(DailySettlement $settlement): array
    {
        $settlement->load(['user', 'closedByUser']);

        $date = $settlement->settlement_date instanceof \DateTimeInterface
            ? $settlement->settlement_date->format('Y-m-d')
            : Carbon::parse((string) $settlement->settlement_date)->toDateString();

        $transactions = Transaction::query()
            ->whereDate('transaction_date', $date)
            ->get(['id', 'reference_number', 'customer_name', 'type', 'payment_method', 'amount', 'ddn_amount']);

        $expenses = Expense::query()
            ->whereDate('expense_date', $date)
            ->with('category:id,name')
            ->get(['id', 'title', 'expense_category_id', 'payment_method', 'amount']);

        return [
            'settlement' => $settlement,
            'transactions' => $transactions,
            'expenses' => $expenses,
        ];
    }

    /**
     * Export the financial report as a CSV file.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $period = $request->query('period', 'this_month');
        [$startDate, $endDate] = $this->resolveDateRange($period, $request);

        $report = $this->calculateFinancialReport($startDate, $endDate);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="financial-report-'.$startDate.'-to-'.$endDate.'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($report, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');

            // Title
            fputcsv($handle, ['DDN RECORD SYSTEM - FINANCIAL REPORT']);
            fputcsv($handle, ['Date Range', $startDate.' to '.$endDate]);
            fputcsv($handle, ['Generated On', now()->toDateTimeString()]);
            fputcsv($handle, []);

            // Summary KPIs
            fputcsv($handle, ['FINANCIAL SUMMARY']);
            fputcsv($handle, ['Metric', 'Amount (PHP)']);
            fputcsv($handle, ['Total Sales (Gross Payments)', number_format($report['summary']['total_sales'], 2, '.', '')]);
            fputcsv($handle, ['Total DDN Share (Net Commission)', number_format($report['summary']['total_ddn'], 2, '.', '')]);
            fputcsv($handle, ['Total Operational Expenses', number_format($report['summary']['total_expenses'], 2, '.', '')]);
            fputcsv($handle, ['Net Operating Income', number_format($report['summary']['net_profit'], 2, '.', '')]);
            fputcsv($handle, ['Cash Collections', number_format($report['summary']['cash_sales'], 2, '.', '')]);
            fputcsv($handle, ['GCash Collections', number_format($report['summary']['gcash_sales'], 2, '.', '')]);
            fputcsv($handle, []);

            // Service Breakdown
            fputcsv($handle, ['SERVICE BREAKDOWN']);
            fputcsv($handle, ['Service Type', 'Transaction Count', 'Total Sales (PHP)', 'DDN Share (PHP)']);
            foreach ($report['servicesBreakdown'] as $service) {
                fputcsv($handle, [
                    ucwords(str_replace('_', ' ', $service['type'])),
                    $service['count'],
                    number_format($service['total_sales'], 2, '.', ''),
                    number_format($service['total_ddn'], 2, '.', ''),
                ]);
            }
            fputcsv($handle, []);

            // Expenses Breakdown
            fputcsv($handle, ['EXPENSES BREAKDOWN BY CATEGORY']);
            fputcsv($handle, ['Category', 'Disbursement Count', 'Total Amount (PHP)']);
            foreach ($report['expensesBreakdown'] as $expense) {
                fputcsv($handle, [
                    $expense['category'],
                    $expense['count'],
                    number_format($expense['total'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Compute full financial breakdown for a date range.
     *
     * @return array{
     *     summary: array<string, float|int>,
     *     servicesBreakdown: array<int, array<string, mixed>>,
     *     expensesBreakdown: array<int, array<string, mixed>>
     * }
     */
    private function calculateFinancialReport(string $startDate, string $endDate): array
    {
        $transactions = Transaction::query()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get(['type', 'payment_method', 'amount', 'ddn_amount']);

        $totalSales = 0.0;
        $totalDdn = 0.0;
        $cashSales = 0.0;
        $gcashSales = 0.0;

        $servicesGrouped = [
            Transaction::TYPE_AIRLINE => ['count' => 0, 'total_sales' => 0.0, 'total_ddn' => 0.0],
            Transaction::TYPE_TRANSLATION => ['count' => 0, 'total_sales' => 0.0, 'total_ddn' => 0.0],
            Transaction::TYPE_PSA => ['count' => 0, 'total_sales' => 0.0, 'total_ddn' => 0.0],
            Transaction::TYPE_ONLINE_ASSISTANCE => ['count' => 0, 'total_sales' => 0.0, 'total_ddn' => 0.0],
            Transaction::TYPE_PRINTING => ['count' => 0, 'total_sales' => 0.0, 'total_ddn' => 0.0],
        ];

        foreach ($transactions as $tx) {
            $amount = (float) $tx->amount;
            $ddn = (float) ($tx->ddn_amount ?? $tx->amount);

            $totalSales += $amount;
            $totalDdn += $ddn;

            if ($tx->payment_method === Transaction::PAYMENT_METHOD_CASH) {
                $cashSales += $amount;
            } elseif ($tx->payment_method === Transaction::PAYMENT_METHOD_GCASH) {
                $gcashSales += $amount;
            }

            if (isset($servicesGrouped[$tx->type])) {
                $servicesGrouped[$tx->type]['count']++;
                $servicesGrouped[$tx->type]['total_sales'] += $amount;
                $servicesGrouped[$tx->type]['total_ddn'] += $ddn;
            }
        }

        $expenses = Expense::query()
            ->with('category:id,name')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get(['expense_category_id', 'payment_method', 'amount']);

        $totalExpenses = 0.0;
        $cashExpenses = 0.0;
        $gcashExpenses = 0.0;
        $bankExpenses = 0.0;

        $categoriesGrouped = [];

        foreach ($expenses as $exp) {
            $amount = (float) $exp->amount;
            $totalExpenses += $amount;

            if ($exp->payment_method === Expense::PAYMENT_METHOD_CASH) {
                $cashExpenses += $amount;
            } elseif ($exp->payment_method === Expense::PAYMENT_METHOD_GCASH) {
                $gcashExpenses += $amount;
            } elseif ($exp->payment_method === Expense::PAYMENT_METHOD_BANK_TRANSFER) {
                $bankExpenses += $amount;
            }

            $catName = $exp->category?->name ?? 'Uncategorized';
            if (! isset($categoriesGrouped[$catName])) {
                $categoriesGrouped[$catName] = ['category' => $catName, 'count' => 0, 'total' => 0.0];
            }
            $categoriesGrouped[$catName]['count']++;
            $categoriesGrouped[$catName]['total'] += $amount;
        }

        usort($categoriesGrouped, fn ($a, $b) => $b['total'] <=> $a['total']);

        $servicesBreakdown = [];
        foreach ($servicesGrouped as $type => $data) {
            $servicesBreakdown[] = [
                'type' => $type,
                'count' => $data['count'],
                'total_sales' => round($data['total_sales'], 2),
                'total_ddn' => round($data['total_ddn'], 2),
            ];
        }

        return [
            'summary' => [
                'total_sales' => round($totalSales, 2),
                'total_ddn' => round($totalDdn, 2),
                'total_expenses' => round($totalExpenses, 2),
                'net_profit' => round($totalDdn - $totalExpenses, 2),
                'cash_sales' => round($cashSales, 2),
                'gcash_sales' => round($gcashSales, 2),
                'cash_expenses' => round($cashExpenses, 2),
                'gcash_expenses' => round($gcashExpenses, 2),
                'bank_expenses' => round($bankExpenses, 2),
                'transaction_count' => $transactions->count(),
                'expense_count' => $expenses->count(),
            ],
            'servicesBreakdown' => $servicesBreakdown,
            'expensesBreakdown' => array_values($categoriesGrouped),
        ];
    }

    /**
     * Resolve a named period or custom dates into [startDate, endDate].
     *
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(string $period, Request $request): array
    {
        $now = Carbon::now();

        if ($period === 'custom') {
            $from = $request->query('date_from', $now->copy()->startOfMonth()->toDateString());
            $to = $request->query('date_to', $now->toDateString());

            return [$from, $to];
        }

        return match ($period) {
            'today' => [
                $now->toDateString(),
                $now->toDateString(),
            ],
            'yesterday' => [
                $now->copy()->subDay()->toDateString(),
                $now->copy()->subDay()->toDateString(),
            ],
            'this_week' => [
                $now->copy()->startOfWeek()->toDateString(),
                $now->copy()->endOfWeek()->toDateString(),
            ],
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth()->toDateString(),
                $now->copy()->subMonth()->endOfMonth()->toDateString(),
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
