<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveLedgerEntryRequest;
use App\Http\Requests\StoreLedgerAdjustmentRequest;
use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GcashLedgerEntry;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GcashLedgerController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * Display listing of GCash ledger entries and balance metrics.
     */
    public function index(Request $request): Response
    {
        $query = GcashLedgerEntry::query()->with(['user', 'transaction', 'expense']);

        $entries = $this->ledgerService->paginateWithOrdering(
            $request,
            $query,
            GcashLedgerEntry::class,
            GcashLedgerEntry::TYPES,
        );

        // Summary metrics
        $currentBalance = GcashLedgerEntry::getCurrentBalance();
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $monthInflows = (float) GcashLedgerEntry::whereIn('type', GcashLedgerEntry::INFLOW_TYPES)
            ->whereBetween('entry_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthOutflows = (float) GcashLedgerEntry::whereIn('type', GcashLedgerEntry::OUTFLOW_TYPES)
            ->whereBetween('entry_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $isLowBalance = $currentBalance < GcashLedgerEntry::LOW_BALANCE_THRESHOLD;

        return Inertia::render('gcash-ledger/Index', [
            'entries' => $entries,
            'metrics' => [
                'current_balance' => $currentBalance,
                'total_inflows_month' => $monthInflows,
                'total_outflows_month' => $monthOutflows,
                'low_balance_threshold' => GcashLedgerEntry::LOW_BALANCE_THRESHOLD,
                'is_low_balance' => $isLowBalance,
            ],
            'filters' => $request->only(['search', 'type', 'date_from', 'date_to', 'sort', 'direction']),
            'types' => GcashLedgerEntry::TYPES,
        ]);
    }

    /**
     * Store a manual balance adjustment or anchor.
     */
    public function storeAdjustment(StoreLedgerAdjustmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $isFromDrawer = $request->boolean('from_drawer') && (float) $validated['amount'] > 0;

        DB::transaction(function () use ($validated, $isFromDrawer) {
            $entry = new GcashLedgerEntry;
            $entry->user_id = (int) auth()->id();
            $entry->type = GcashLedgerEntry::TYPE_ADJUSTMENT;
            $entry->amount = $validated['amount'];
            $entry->entry_date = $validated['entry_date'];
            $entry->notes = $isFromDrawer
                ? 'GCash Top-Up from Cash Drawer: '.$validated['notes']
                : 'Adjustment: '.$validated['notes'];

            if ($isFromDrawer) {
                $category = ExpenseCategory::firstOrCreate(
                    ['name' => ExpenseCategory::GCASH_WALLET_TOP_UP],
                    [
                        'description' => 'Internal cash drawer funds transferred to business GCash wallet',
                        'is_active' => true,
                        'sort_order' => 11,
                    ]
                );
                $expense = Expense::create([
                    'user_id' => (int) auth()->id(),
                    'expense_category_id' => $category->id,
                    'title' => 'GCash Top-Up from Drawer',
                    'amount' => $validated['amount'],
                    'expense_date' => $validated['entry_date'],
                    'payment_method' => Expense::PAYMENT_METHOD_CASH,
                    'payee' => 'GCash Wallet',
                    'notes' => 'Drawer cash withdrawn to top up GCash: '.$validated['notes'],
                ]);
                $entry->expense_id = $expense->id;
            }

            if (! empty($validated['is_balance_anchor'])) {
                $entry->is_balance_anchor = true;
                $entry->running_balance = $validated['running_balance'] ?? $validated['amount'];
            }

            if (str_contains(strtolower($validated['notes']), 'initial balance')) {
                $minOrder = GcashLedgerEntry::whereDate('entry_date', $validated['entry_date'])->min('sort_order');
                $entry->sort_order = $minOrder !== null ? min(0, (int) $minOrder - 1) : 1;
            } else {
                $maxOrder = (int) (GcashLedgerEntry::whereDate('entry_date', $validated['entry_date'])->max('sort_order') ?? 0);
                $entry->sort_order = $maxOrder + 1;
            }

            $entry->save();

            GcashLedgerEntry::recalculateRunningBalances();
            DailySettlement::syncForDate($validated['entry_date']);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isFromDrawer
                ? 'GCash top-up recorded and cash drawer withdrawal created successfully.'
                : 'GCash balance adjustment applied successfully.',
        ]);

        return back();
    }

    /**
     * Update an existing GCash ledger entry (adjustment or anchor).
     */
    public function update(Request $request, GcashLedgerEntry $entry): RedirectResponse
    {
        if ($entry->transaction_id && ! in_array($entry->type, [GcashLedgerEntry::TYPE_REMITTANCE_CASH_IN, GcashLedgerEntry::TYPE_REMITTANCE_CASH_OUT], true)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This entry is linked to a transaction. Please edit the transaction directly.',
            ]);

            return back();
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2'],
            'entry_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_balance_anchor' => ['nullable', 'boolean'],
            'running_balance' => ['nullable', 'numeric', 'decimal:0,2'],
        ]);

        $oldDate = $entry->entry_date->toDateString();

        DB::transaction(function () use ($request, $entry, $validated, $oldDate) {
            $entry->amount = $validated['amount'];
            $entry->entry_date = $validated['entry_date'];
            $entry->notes = $validated['notes'] ?? null;

            if ($request->has('is_balance_anchor')) {
                $entry->is_balance_anchor = (bool) $validated['is_balance_anchor'];
                if ($entry->is_balance_anchor && isset($validated['running_balance'])) {
                    $entry->running_balance = $validated['running_balance'];
                }
            }

            $entry->save();

            if ($entry->type === GcashLedgerEntry::TYPE_ADJUSTMENT && $entry->expense) {
                $entry->expense->amount = abs((float) $validated['amount']);
                $entry->expense->expense_date = $validated['entry_date'];
                $entry->expense->save();
            }

            GcashLedgerEntry::recalculateRunningBalances();

            if ($oldDate && $oldDate !== $validated['entry_date']) {
                DailySettlement::syncForDate($oldDate);
            }
            DailySettlement::syncForDate($validated['entry_date']);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'GCash ledger entry updated.',
        ]);

        return back();
    }

    /**
     * Remove a manual GCash entry.
     */
    public function destroy(GcashLedgerEntry $entry): RedirectResponse
    {
        if ($entry->transaction_id || $entry->expense_id) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Linked entries cannot be deleted from here. Please delete the original transaction or expense.',
            ]);

            return back();
        }

        DB::transaction(function () use ($entry) {
            $entryDate = $entry->entry_date->toDateString();
            $entry->delete();

            GcashLedgerEntry::recalculateRunningBalances();
            DailySettlement::syncForDate($entryDate);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Entry removed from GCash ledger.',
        ]);

        return back();
    }

    /**
     * Move an entry up or down in sort order.
     */
    public function move(MoveLedgerEntryRequest $request, GcashLedgerEntry $entry): RedirectResponse
    {
        $validated = $request->validated();

        $moved = $this->ledgerService->moveEntryWithinDate(
            GcashLedgerEntry::class,
            $entry,
            $validated['direction'],
            $validated['table_direction'] ?? 'desc',
        );

        if ($moved) {
            GcashLedgerEntry::recalculateRunningBalances();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Entry order updated. Balances recalculated.',
            ]);
        }

        return back();
    }
}
