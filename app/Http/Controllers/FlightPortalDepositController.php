<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveLedgerEntryRequest;
use App\Http\Requests\StoreLedgerAdjustmentRequest;
use App\Http\Requests\StorePortalTopUpRequest;
use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FlightPortalDeposit;
use App\Models\GcashLedgerEntry;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FlightPortalDepositController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * Display a listing of flight portal deposits and live balance metrics.
     */
    public function index(Request $request): Response
    {
        $query = FlightPortalDeposit::query()->with(['user', 'transaction', 'expense']);

        $deposits = $this->ledgerService->paginateWithOrdering(
            $request,
            $query,
            FlightPortalDeposit::class,
            FlightPortalDeposit::TYPES,
        );

        // Calculate summary metrics
        $currentBalance = FlightPortalDeposit::getCurrentBalance();
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $monthTopUps = (float) FlightPortalDeposit::where('type', FlightPortalDeposit::TYPE_TOP_UP)
            ->whereBetween('entry_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthDeductions = (float) FlightPortalDeposit::where('type', FlightPortalDeposit::TYPE_TICKET_DEDUCTION)
            ->whereBetween('entry_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $lowBalanceThreshold = FlightPortalDeposit::LOW_BALANCE_THRESHOLD;
        $isLowBalance = $currentBalance < $lowBalanceThreshold;

        return Inertia::render('portal-deposits/Index', [
            'deposits' => $deposits,
            'metrics' => [
                'current_balance' => $currentBalance,
                'total_top_ups_month' => $monthTopUps,
                'total_deductions_month' => $monthDeductions,
                'low_balance_threshold' => $lowBalanceThreshold,
                'is_low_balance' => $isLowBalance,
            ],
            'filters' => $request->only(['search', 'type', 'date_from', 'date_to', 'sort', 'direction']),
            'paymentSources' => FlightPortalDeposit::PAYMENT_SOURCES,
        ]);
    }

    /**
     * Store a new top-up entry.
     */
    public function storeTopUp(StorePortalTopUpRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $expenseId = null;

            if (! empty($validated['record_as_expense'])) {
                $category = ExpenseCategory::firstOrCreate(
                    ['name' => ExpenseCategory::FLIGHT_PORTAL_RELOAD],
                    [
                        'description' => 'Funds deposited to third-party flight booking platform',
                        'is_active' => true,
                        'sort_order' => 10,
                    ]
                );

                $expense = Expense::create([
                    'title' => 'Flight Portal Top-up'.(! empty($validated['reference_number']) ? ' ('.$validated['reference_number'].')' : ''),
                    'expense_category_id' => $category->id,
                    'amount' => $validated['amount'],
                    'expense_date' => $validated['entry_date'],
                    'payment_method' => $validated['payment_source'],
                    'payee' => 'Flight Booking Portal',
                    'reference_number' => $validated['reference_number'] ?? null,
                    'recurring_frequency' => 'none',
                    'notes' => $validated['notes'] ?? null,
                    'user_id' => auth()->id(),
                ]);

                $expenseId = $expense->id;

                GcashLedgerEntry::syncForExpense($expense);

                // Sync daily settlement if exists
                DailySettlement::syncForDate($validated['entry_date']);
            }

            $deposit = new FlightPortalDeposit;
            $deposit->user_id = (int) auth()->id();
            $deposit->type = FlightPortalDeposit::TYPE_TOP_UP;
            $deposit->amount = $validated['amount'];
            $deposit->entry_date = $validated['entry_date'];
            $deposit->payment_source = $validated['payment_source'];
            $deposit->reference_number = $validated['reference_number'] ?? null;
            $deposit->notes = $validated['notes'] ?? null;
            $deposit->expense_id = $expenseId;

            $minOrder = FlightPortalDeposit::whereDate('entry_date', $validated['entry_date'])->min('sort_order');
            $deposit->sort_order = $minOrder !== null ? min(0, (int) $minOrder - 1) : 1;

            $deposit->save();

            FlightPortalDeposit::recalculateRunningBalances();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Portal top-up of ₱'.number_format((float) $validated['amount'], 2).' recorded successfully.',
        ]);

        return back();
    }

    /**
     * Store a manual balance adjustment.
     */
    public function storeAdjustment(StoreLedgerAdjustmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $deposit = new FlightPortalDeposit;
            $deposit->user_id = (int) auth()->id();
            $deposit->type = FlightPortalDeposit::TYPE_ADJUSTMENT;
            $deposit->amount = $validated['amount'];
            $deposit->entry_date = $validated['entry_date'];
            $deposit->notes = 'Adjustment: '.$validated['notes'];

            if (str_contains(strtolower($validated['notes']), 'initial balance')) {
                $minOrder = FlightPortalDeposit::whereDate('entry_date', $validated['entry_date'])->min('sort_order');
                $deposit->sort_order = $minOrder !== null ? min(0, (int) $minOrder - 1) : 1;
            } else {
                $maxOrder = (int) (FlightPortalDeposit::whereDate('entry_date', $validated['entry_date'])->max('sort_order') ?? 0);
                $deposit->sort_order = $maxOrder + 1;
            }

            $deposit->save();

            FlightPortalDeposit::recalculateRunningBalances();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Balance adjustment of ₱'.number_format((float) $validated['amount'], 2).' applied.',
        ]);

        return back();
    }

    /**
     * Update an existing deposit entry.
     */
    public function update(Request $request, FlightPortalDeposit $deposit): RedirectResponse
    {
        if ($deposit->type === FlightPortalDeposit::TYPE_TICKET_DEDUCTION && $deposit->transaction_id) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This deduction is linked to an airline transaction. Please edit the transaction directly.',
            ]);

            return back();
        }

        $validated = match ($deposit->type) {
            FlightPortalDeposit::TYPE_TOP_UP => $request->validate([
                'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
                'entry_date' => ['required', 'date'],
                'payment_source' => ['required', 'string', 'in:cash,gcash,bank_transfer'],
                'reference_number' => ['nullable', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]),
            FlightPortalDeposit::TYPE_ADJUSTMENT => $request->validate([
                'amount' => ['required', 'numeric', 'decimal:0,2', 'not_in:0'],
                'entry_date' => ['required', 'date'],
                'notes' => ['required', 'string', 'max:1000'],
            ]),
            default => $request->validate([
                'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
                'entry_date' => ['required', 'date'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]),
        };

        $request->validate([
            'running_balance' => ['nullable', 'numeric', 'decimal:0,2'],
            'is_balance_anchor' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $deposit, $validated) {
            if ($deposit->type === FlightPortalDeposit::TYPE_TOP_UP) {
                $oldDate = $deposit->entry_date->toDateString();

                $deposit->amount = $validated['amount'];
                $deposit->entry_date = $validated['entry_date'];
                $deposit->payment_source = $validated['payment_source'];
                $deposit->reference_number = $validated['reference_number'] ?? null;
                $deposit->notes = $validated['notes'] ?? null;
                $deposit->save();

                // Synchronize linked expense if exists
                if ($deposit->expense) {
                    $expense = $deposit->expense;
                    $expense->amount = $validated['amount'];
                    $expense->expense_date = $validated['entry_date'];
                    $expense->payment_method = $validated['payment_source'];
                    $expense->reference_number = $validated['reference_number'] ?? null;
                    $expense->title = 'Flight Portal Top-up'.(! empty($validated['reference_number']) ? ' ('.$validated['reference_number'].')' : '');
                    $expense->notes = $validated['notes'] ?? null;
                    $expense->save();

                    GcashLedgerEntry::syncForExpense($expense);

                    if ($oldDate) {
                        DailySettlement::syncForDate($oldDate);
                    }
                    DailySettlement::syncForDate($validated['entry_date']);
                }
            } elseif ($deposit->type === FlightPortalDeposit::TYPE_ADJUSTMENT) {
                $deposit->amount = $validated['amount'];
                $deposit->entry_date = $validated['entry_date'];
                $deposit->notes = $validated['notes'];
                $deposit->save();
            } else {
                $deposit->amount = $validated['amount'];
                $deposit->entry_date = $validated['entry_date'];
                $deposit->notes = $validated['notes'] ?? null;
                $deposit->save();
            }

            if ($request->has('is_balance_anchor')) {
                $isAnchor = $request->boolean('is_balance_anchor');
                $deposit->is_balance_anchor = $isAnchor;

                if ($isAnchor && $request->filled('running_balance')) {
                    $deposit->running_balance = number_format((float) $request->input('running_balance'), 2, '.', '');
                }

                $deposit->save();
            }

            FlightPortalDeposit::recalculateRunningBalances();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deposit entry updated successfully and balances recalculated.',
        ]);

        return back();
    }

    /**
     * Delete a deposit entry.
     */
    public function destroy(FlightPortalDeposit $deposit): RedirectResponse
    {
        if ($deposit->type === FlightPortalDeposit::TYPE_TICKET_DEDUCTION && $deposit->transaction_id) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This deduction is linked to an airline transaction. Please edit or delete the transaction directly.',
            ]);

            return back();
        }

        DB::transaction(function () use ($deposit) {
            // If it was linked to an expense, soft delete the expense as well
            if ($deposit->expense) {
                $expenseId = $deposit->expense->id;
                $deposit->expense->delete();
                GcashLedgerEntry::where('expense_id', $expenseId)->delete();
                GcashLedgerEntry::recalculateRunningBalances();
                DailySettlement::syncForDate($deposit->entry_date->toDateString());
            }

            $deposit->delete();

            FlightPortalDeposit::recalculateRunningBalances();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Ledger entry deleted and balances updated.',
        ]);

        return back();
    }

    /**
     * Move a deposit entry up or down in same-day sequence.
     */
    public function move(MoveLedgerEntryRequest $request, FlightPortalDeposit $deposit): RedirectResponse
    {
        $validated = $request->validated();

        $moved = $this->ledgerService->moveEntryWithinDate(
            FlightPortalDeposit::class,
            $deposit,
            $validated['direction'],
            $validated['table_direction'] ?? 'desc',
        );

        if ($moved) {
            FlightPortalDeposit::recalculateRunningBalances();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Entry order updated. Balances recalculated.',
            ]);
        }

        return back();
    }
}
