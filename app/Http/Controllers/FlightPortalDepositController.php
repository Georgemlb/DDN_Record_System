<?php

namespace App\Http\Controllers;

use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FlightPortalDeposit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FlightPortalDepositController extends Controller
{
    /**
     * Display a listing of flight portal deposits and live balance metrics.
     */
    public function index(Request $request): Response
    {
        $query = FlightPortalDeposit::query()->with(['user', 'transaction', 'expense']);

        // Search filter (reference number or notes)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('transaction', function ($tq) use ($search) {
                        $tq->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('reference_number', 'like', "%{$search}%");
                    });
            });
        }

        // Type filter
        if ($type = $request->input('type')) {
            if ($type !== 'all' && in_array($type, FlightPortalDeposit::TYPES)) {
                $query->where('type', $type);
            }
        }

        // Date range filter
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('entry_date', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('entry_date', '<=', $dateTo);
        }

        // Sorting
        $sort = $request->input('sort', 'entry_date');
        $direction = $request->input('direction', 'desc');

        if (in_array($sort, ['entry_date', 'amount', 'running_balance', 'type', 'created_at'])) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('entry_date', 'desc');
        }

        // Secondary sort to stabilize pagination
        if ($sort !== 'id') {
            $query->orderBy('id', 'desc');
        }

        $deposits = $query->paginate(15)->withQueryString();

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
    public function storeTopUp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'entry_date' => ['required', 'date'],
            'payment_source' => ['required', 'string', 'in:cash,gcash,bank_transfer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'record_as_expense' => ['nullable', 'boolean'],
        ]);

        $expenseId = null;

        if (! empty($validated['record_as_expense'])) {
            $category = ExpenseCategory::firstOrCreate(
                ['name' => 'Flight Portal Reload'],
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

            // Sync daily settlement if exists
            DailySettlement::syncForDate($validated['entry_date']);
        }

        $deposit = new FlightPortalDeposit;
        $deposit->user_id = auth()->id();
        $deposit->type = FlightPortalDeposit::TYPE_TOP_UP;
        $deposit->amount = $validated['amount'];
        $deposit->entry_date = $validated['entry_date'];
        $deposit->payment_source = $validated['payment_source'];
        $deposit->reference_number = $validated['reference_number'] ?? null;
        $deposit->notes = $validated['notes'] ?? null;
        $deposit->expense_id = $expenseId;
        $deposit->save();

        FlightPortalDeposit::recalculateRunningBalances();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Portal top-up of ₱'.number_format((float) $validated['amount'], 2).' recorded successfully.',
        ]);

        return back();
    }

    /**
     * Store a manual balance adjustment.
     */
    public function storeAdjustment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2'],
            'entry_date' => ['required', 'date'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $deposit = new FlightPortalDeposit;
        $deposit->user_id = auth()->id();
        $deposit->type = FlightPortalDeposit::TYPE_ADJUSTMENT;
        $deposit->amount = $validated['amount'];
        $deposit->entry_date = $validated['entry_date'];
        $deposit->notes = 'Adjustment: '.$validated['notes'];
        $deposit->save();

        FlightPortalDeposit::recalculateRunningBalances();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Balance adjustment of ₱'.number_format((float) $validated['amount'], 2).' applied.',
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

        // If it was linked to an expense, soft delete the expense as well
        if ($deposit->expense) {
            $deposit->expense->delete();
            DailySettlement::syncForDate($deposit->entry_date->toDateString());
        }

        $deposit->delete();

        FlightPortalDeposit::recalculateRunningBalances();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Ledger entry deleted and balances updated.',
        ]);

        return back();
    }
}
