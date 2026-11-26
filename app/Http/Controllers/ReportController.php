<?php

namespace App\Http\Controllers;

use App\Http\Requests\CloseSettlementRequest;
use App\Http\Requests\StorePastSettlementRequest;
use App\Http\Requests\StoreSettlementRequest;
use App\Http\Requests\UpdateSettlementRequest;
use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Services\ActivityLogger;
use App\Services\FinancialMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly FinancialMetricsService $metricsService,
    ) {}

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

        $suggestedOpeningGcash = (float) (DailySettlement::query()
            ->where('status', DailySettlement::STATUS_CLOSED)
            ->whereNotNull('actual_gcash')
            ->orderByDesc('settlement_date')
            ->value('actual_gcash') ?? 0);

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
                'suggestedOpeningGcash' => $suggestedOpeningGcash,
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
    public function startDay(StoreSettlementRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $date = $validated['settlement_date'] ?? now()->toDateString();

        $existing = DailySettlement::query()->whereDate('settlement_date', $date)->first();
        if ($existing) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'A settlement for this date already exists.']);

            return back();
        }

        DB::transaction(function () use ($validated, $date) {
            $openingCash = (float) $validated['opening_cash'];
            $openingGcash = (float) ($validated['opening_gcash'] ?? 0);
            $metrics = DailySettlement::calculateMetricsForDate($date);

            DailySettlement::query()->create([
                'settlement_date' => $date,
                'user_id' => auth()->id(),
                'status' => DailySettlement::STATUS_OPEN,
                'opening_cash' => $openingCash,
                'cash_in' => $metrics['cash_in'],
                'cash_out' => $metrics['cash_out'],
                'expected_cash' => round($openingCash + $metrics['cash_in'] - $metrics['cash_out'], 2),
                'opening_gcash' => $openingGcash,
                'gcash_in' => $metrics['gcash_in'],
                'gcash_out' => $metrics['gcash_out'],
                'expected_gcash' => round($openingGcash + $metrics['gcash_in'] - $metrics['gcash_out'], 2),
                'total_gcash' => $metrics['total_gcash'],
                'total_bank_transfer' => $metrics['total_bank_transfer'],
                'total_sales' => $metrics['total_sales'],
                'total_ddn_earnings' => $metrics['total_ddn_earnings'],
                'opened_at' => now(),
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Daily cash register opened with ₱'.number_format((float) $validated['opening_cash'], 2).' float.',
        ]);

        return back();
    }

    /**
     * Preview metrics and suggested opening floats for a past date.
     */
    public function previewPastSettlement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();

        $exists = DailySettlement::query()->whereDate('settlement_date', $date)->exists();
        if ($exists) {
            return response()->json([
                'exists' => true,
            ]);
        }

        $metrics = DailySettlement::calculateMetricsForDate($date);

        $suggestedOpeningCash = (float) (DailySettlement::query()
            ->where('status', DailySettlement::STATUS_CLOSED)
            ->whereDate('settlement_date', '<', $date)
            ->orderByDesc('settlement_date')
            ->value('actual_cash') ?? 0);

        $suggestedOpeningGcash = (float) (DailySettlement::query()
            ->where('status', DailySettlement::STATUS_CLOSED)
            ->whereDate('settlement_date', '<', $date)
            ->whereNotNull('actual_gcash')
            ->orderByDesc('settlement_date')
            ->value('actual_gcash') ?? 0);

        return response()->json([
            'exists' => false,
            'metrics' => $metrics,
            'suggested_opening_cash' => $suggestedOpeningCash,
            'suggested_opening_gcash' => $suggestedOpeningGcash,
        ]);
    }

    /**
     * Record a past daily cash settlement directly as closed.
     */
    public function storePastSettlement(StorePastSettlementRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $date = Carbon::parse($validated['settlement_date'])->toDateString();
        $openingCash = (float) $validated['opening_cash'];
        $openingGcash = (float) ($validated['opening_gcash'] ?? 0);
        $actualCash = (float) $validated['actual_cash'];
        $actualGcash = is_numeric($validated['actual_gcash'] ?? null)
            ? (float) $validated['actual_gcash']
            : null;

        DB::transaction(function () use ($date, $openingCash, $openingGcash, $actualCash, $actualGcash, $validated) {
            $metrics = DailySettlement::calculateMetricsForDate($date);

            $expectedCash = round($openingCash + $metrics['cash_in'] - $metrics['cash_out'], 2);
            $discrepancy = round($actualCash - $expectedCash, 2);

            $expectedGcash = round($openingGcash + $metrics['gcash_in'] - $metrics['gcash_out'], 2);
            $gcashDiscrepancy = $actualGcash !== null ? round($actualGcash - $expectedGcash, 2) : null;

            DailySettlement::query()->create([
                'settlement_date' => $date,
                'user_id' => auth()->id(),
                'closed_by_user_id' => auth()->id(),
                'status' => DailySettlement::STATUS_CLOSED,
                'opening_cash' => $openingCash,
                'cash_in' => $metrics['cash_in'],
                'cash_out' => $metrics['cash_out'],
                'expected_cash' => $expectedCash,
                'actual_cash' => $actualCash,
                'discrepancy' => $discrepancy,
                'opening_gcash' => $openingGcash,
                'gcash_in' => $metrics['gcash_in'],
                'gcash_out' => $metrics['gcash_out'],
                'expected_gcash' => $expectedGcash,
                'actual_gcash' => $actualGcash,
                'gcash_discrepancy' => $gcashDiscrepancy,
                'total_gcash' => $metrics['total_gcash'],
                'total_bank_transfer' => $metrics['total_bank_transfer'],
                'total_sales' => $metrics['total_sales'],
                'total_ddn_earnings' => $metrics['total_ddn_earnings'],
                'notes' => $validated['notes'] ?? null,
                'opened_at' => Carbon::parse($date)->startOfDay(),
                'closed_at' => now(),
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Past daily cash settlement for '.Carbon::parse($date)->format('M d, Y').' recorded successfully.',
        ]);

        return back();
    }

    /**
     * Update an existing daily cash settlement record.
     */
    public function update(UpdateSettlementRequest $request, DailySettlement $settlement): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $settlement, $validated) {
            $settlement->opening_cash = (float) $validated['opening_cash'];
            if (array_key_exists('opening_gcash', $validated)) {
                $settlement->opening_gcash = (float) ($validated['opening_gcash'] ?? 0);
            }
            $settlement->notes = $validated['notes'] ?? null;
            $newStatus = $validated['status'];

            if ($request->boolean('sync_metrics', true)) {
                $settlement->syncMetricsWithRecords();
            } else {
                $settlement->expected_cash = round((float) $settlement->opening_cash + (float) $settlement->cash_in - (float) $settlement->cash_out, 2);
                $settlement->expected_gcash = round((float) ($settlement->opening_gcash ?? 0) + (float) ($settlement->gcash_in ?? 0) - (float) ($settlement->gcash_out ?? 0), 2);
            }

            if ($newStatus === DailySettlement::STATUS_CLOSED) {
                $actualCash = $validated['actual_cash'] !== null
                    ? (float) $validated['actual_cash']
                    : (float) ($settlement->actual_cash ?? $settlement->expected_cash);

                $settlement->actual_cash = $actualCash;
                $settlement->discrepancy = round($actualCash - (float) $settlement->expected_cash, 2);

                if (array_key_exists('actual_gcash', $validated) && $validated['actual_gcash'] !== null) {
                    $actualGcash = (float) $validated['actual_gcash'];
                    $settlement->actual_gcash = $actualGcash;
                    $settlement->gcash_discrepancy = round($actualGcash - (float) $settlement->expected_gcash, 2);
                }

                $settlement->status = DailySettlement::STATUS_CLOSED;
                if (! $settlement->closed_at) {
                    $settlement->closed_at = now();
                    $settlement->closed_by_user_id = (int) auth()->id();
                }
            } else {
                $settlement->status = DailySettlement::STATUS_OPEN;
                $settlement->closed_at = null;
                $settlement->closed_by_user_id = null;
                if ($validated['actual_cash'] !== null) {
                    $settlement->actual_cash = (float) $validated['actual_cash'];
                    $settlement->discrepancy = round((float) $settlement->actual_cash - (float) $settlement->expected_cash, 2);
                }
                if (array_key_exists('actual_gcash', $validated) && $validated['actual_gcash'] !== null) {
                    $settlement->actual_gcash = (float) $validated['actual_gcash'];
                    $settlement->gcash_discrepancy = round((float) $settlement->actual_gcash - (float) $settlement->expected_gcash, 2);
                }
            }

            $settlement->save();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Daily settlement for '.$settlement->settlement_date->format('M d, Y').' updated successfully.',
        ]);

        return back();
    }

    /**
     * Close the daily settlement drawer and calculate discrepancy.
     */
    public function closeDay(CloseSettlementRequest $request, DailySettlement $settlement): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($settlement, $validated) {
            $actualCash = (float) $validated['actual_cash'];
            $settlement->actual_cash = $actualCash;
            if (array_key_exists('actual_gcash', $validated) && $validated['actual_gcash'] !== null) {
                $settlement->actual_gcash = (float) $validated['actual_gcash'];
            }
            $settlement->syncMetricsWithRecords();

            $settlement->status = DailySettlement::STATUS_CLOSED;
            $settlement->closed_by_user_id = (int) auth()->id();
            $settlement->closed_at = now();
            if (array_key_exists('notes', $validated)) {
                $settlement->notes = $validated['notes'];
            }
            $settlement->save();
        });

        ActivityLogger::log(
            'settlement',
            'closed',
            'Closed daily settlement for '.$settlement->settlement_date->format('M d, Y'),
            $settlement,
        );

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

        ActivityLogger::log(
            'settlement',
            'reopened',
            'Reopened daily settlement for '.$settlement->settlement_date->format('M d, Y'),
            $settlement,
        );

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => 'Daily register reopened for adjustments.',
        ]);

        return back();
    }

    /**
     * Permanently delete a daily cash settlement record.
     */
    public function destroy(DailySettlement $settlement): RedirectResponse
    {
        $date = $settlement->settlement_date->format('M d, Y');
        $settlement->forceDelete();

        ActivityLogger::log(
            'settlement',
            'deleted',
            "Deleted daily settlement for {$date}",
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Daily cash settlement for {$date} deleted successfully.",
        ]);

        return back();
    }

    /**
     * Retrieve slip data for viewing and printing.
     *
     * @return array<string, mixed>
     */
    public function showSlip(DailySettlement $settlement): array
    {
        $settlement->load(['user', 'closedByUser']);

        $date = $settlement->settlement_date->format('Y-m-d');

        $transactions = Transaction::query()
            ->whereDate('transaction_date', $date)
            ->get(['id', 'reference_number', 'customer_name', 'type', 'payment_method', 'payment_status', 'amount', 'amount_paid', 'balance', 'ddn_amount', 'employee_share']);

        $balanceCollections = TransactionPayment::query()
            ->whereDate('payment_date', $date)
            ->whereHas('transaction', function ($q) use ($date) {
                $q->whereDate('transaction_date', '!=', $date);
            })
            ->with('transaction:id,reference_number,customer_name,type,transaction_date')
            ->get(['id', 'transaction_id', 'amount', 'payment_method', 'payment_date', 'notes']);

        $expenses = Expense::query()
            ->whereDate('expense_date', $date)
            ->with('category:id,name')
            ->get(['id', 'title', 'expense_category_id', 'payment_method', 'amount', 'is_personal', 'reimbursement_status']);

        $reimbursements = Expense::query()
            ->where('is_personal', true)
            ->where('reimbursement_status', Expense::REIMBURSEMENT_STATUS_REIMBURSED)
            ->whereDate('reimbursed_at', $date)
            ->with('category:id,name')
            ->get(['id', 'title', 'expense_category_id', 'amount', 'reimbursement_method', 'reimbursed_at', 'payee']);

        return [
            'settlement' => $settlement,
            'transactions' => $transactions,
            'balance_collections' => $balanceCollections,
            'expenses' => $expenses,
            'reimbursements' => $reimbursements,
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
            if ($handle === false) {
                return;
            }

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
            fputcsv($handle, ['Operating Expenses', number_format($report['summary']['operating_expenses'] ?? $report['summary']['total_expenses'], 2, '.', '')]);
            fputcsv($handle, ['Portal Capital Reloads', number_format($report['summary']['portal_reloads'] ?? 0, 2, '.', '')]);
            fputcsv($handle, ['Total Disbursements', number_format($report['summary']['total_expenses'], 2, '.', '')]);
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
            ->get(['type', 'payment_method', 'amount', 'ddn_amount', 'cash_amount', 'gcash_amount']);

        $totalSales = 0.0;
        $totalDdn = 0.0;

        $servicesGrouped = [
            Transaction::TYPE_AIRLINE => ['count' => 0, 'total_sales' => 0.0, 'total_ddn' => 0.0],
            Transaction::TYPE_FERRY => ['count' => 0, 'total_sales' => 0.0, 'total_ddn' => 0.0],
            Transaction::TYPE_TRANSLATION => ['count' => 0, 'total_sales' => 0.0, 'total_ddn' => 0.0],
            Transaction::TYPE_PSA => ['count' => 0, 'total_sales' => 0.0, 'total_ddn' => 0.0],
            Transaction::TYPE_ONLINE_ASSISTANCE => ['count' => 0, 'total_sales' => 0.0, 'total_ddn' => 0.0],
            Transaction::TYPE_PRINTING => ['count' => 0, 'total_sales' => 0.0, 'total_ddn' => 0.0],
            Transaction::TYPE_REMITTANCE => ['count' => 0, 'total_sales' => 0.0, 'total_ddn' => 0.0],
        ];

        foreach ($transactions as $tx) {
            $amount = (float) $tx->amount;
            $ddn = (float) ($tx->ddn_amount ?? $tx->amount);

            $totalSales += $amount;
            $totalDdn += $ddn;

            if (isset($servicesGrouped[$tx->type])) {
                $servicesGrouped[$tx->type]['count']++;
                $servicesGrouped[$tx->type]['total_sales'] += $amount;
                $servicesGrouped[$tx->type]['total_ddn'] += $ddn;
            }
        }

        $collections = $this->metricsService->calculateCashAndGcashCollections($startDate, $endDate);
        $expenseMetrics = $this->metricsService->calculatePeriodExpenses($startDate, $endDate);

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
                'total_expenses' => $expenseMetrics['total_expenses'],
                'operating_expenses' => $expenseMetrics['operating_expenses'],
                'portal_reloads' => $expenseMetrics['portal_reloads'],
                'gcash_top_ups' => $expenseMetrics['gcash_top_ups'],
                'net_profit' => round($totalDdn - $expenseMetrics['operating_expenses'], 2),
                'cash_sales' => $collections['cash'],
                'gcash_sales' => $collections['gcash'],
                'cash_expenses' => $expenseMetrics['cash_expenses'],
                'gcash_expenses' => $expenseMetrics['gcash_expenses'],
                'bank_expenses' => $expenseMetrics['bank_expenses'],
                'transaction_count' => $transactions->count(),
                'expense_count' => $expenseMetrics['expense_count'],
            ],
            'servicesBreakdown' => $servicesBreakdown,
            'expensesBreakdown' => $expenseMetrics['categories_breakdown'],
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
