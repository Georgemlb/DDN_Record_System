<?php

namespace App\Http\Controllers;

use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GcashLedgerEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class GcashLedgerController extends Controller
{
    /**
     * Display listing of GCash ledger entries and balance metrics.
     */
    public function index(Request $request): Response
    {
        $query = GcashLedgerEntry::query()->with(['user', 'transaction', 'expense']);

        // Search filter
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
            if ($type !== 'all' && in_array($type, GcashLedgerEntry::TYPES, true)) {
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
        $sort = (string) $request->input('sort', 'entry_date');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, ['amount', 'running_balance', 'type', 'created_at'], true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderByRaw('date(entry_date) '.$direction);
        }

        if ($sort === 'entry_date' || ! $request->has('sort')) {
            $query->orderBy('sort_order', $direction);
        }

        if ($sort !== 'id') {
            $query->orderBy('id', $direction);
        }

        $entries = $query->paginate(15)->withQueryString();

        $pageDates = $entries->getCollection()->map(function ($d) {
            return Carbon::parse($d->entry_date)->toDateString();
        })->unique()->values()->all();

        $dateStats = GcashLedgerEntry::whereIn('entry_date', $pageDates)
            ->selectRaw('entry_date, MIN(sort_order) as min_order, MAX(sort_order) as max_order, COUNT(*) as entry_count')
            ->groupBy('entry_date')
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->entry_date)->toDateString();
            });

        $entries->getCollection()->transform(function ($entry) use ($dateStats, $direction) {
            $dateStr = Carbon::parse($entry->entry_date)->toDateString();
            $stat = $dateStats->get($dateStr);
            if (! $stat || (int) $stat->getAttribute('entry_count') <= 1) {
                $entry->can_move_up = false;
                $entry->can_move_down = false;

                return $entry;
            }

            $maxOrder = (int) $stat->getAttribute('max_order');
            $minOrder = (int) $stat->getAttribute('min_order');

            if ($direction === 'desc') {
                $entry->can_move_up = $entry->sort_order < $maxOrder;
                $entry->can_move_down = $entry->sort_order > $minOrder;
            } else {
                $entry->can_move_up = $entry->sort_order > $minOrder;
                $entry->can_move_down = $entry->sort_order < $maxOrder;
            }

            return $entry;
        });

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
    public function storeAdjustment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2'],
            'entry_date' => ['required', 'date'],
            'notes' => ['required', 'string', 'max:1000'],
            'is_balance_anchor' => ['nullable', 'boolean'],
            'running_balance' => ['nullable', 'numeric', 'decimal:0,2'],
        ]);

        $isFromDrawer = $request->boolean('from_drawer') && (float) $validated['amount'] > 0;

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
                ['name' => 'Miscellaneous'],
                ['is_active' => true, 'sort_order' => 1]
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

        $entryDate = $entry->entry_date->toDateString();
        $entry->delete();

        GcashLedgerEntry::recalculateRunningBalances();
        DailySettlement::syncForDate($entryDate);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Entry removed from GCash ledger.',
        ]);

        return back();
    }

    /**
     * Move an entry up or down in sort order.
     */
    public function move(Request $request, GcashLedgerEntry $entry): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:up,down'],
            'table_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $visualDirection = $validated['direction'];
        $tableDirection = $validated['table_direction'] ?? 'desc';
        $entryDate = $entry->entry_date->toDateString();

        $dayEntries = GcashLedgerEntry::whereDate('entry_date', $entryDate)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($dayEntries->count() <= 1) {
            return back();
        }

        // Re-normalize sort_orders to 1, 2, 3... to avoid ties or gaps
        foreach ($dayEntries as $index => $item) {
            $expectedOrder = $index + 1;
            if ($item->sort_order !== $expectedOrder) {
                $item->sort_order = $expectedOrder;
                $item->saveQuietly();
            }
        }

        $currentIndex = $dayEntries->search(fn ($item) => $item->id === $entry->id);

        if ($currentIndex === false) {
            return back();
        }

        $targetIndex = null;
        if ($tableDirection === 'desc') {
            if ($visualDirection === 'up' && $currentIndex < $dayEntries->count() - 1) {
                $targetIndex = $currentIndex + 1;
            } elseif ($visualDirection === 'down' && $currentIndex > 0) {
                $targetIndex = $currentIndex - 1;
            }
        } else {
            if ($visualDirection === 'up' && $currentIndex > 0) {
                $targetIndex = $currentIndex - 1;
            } elseif ($visualDirection === 'down' && $currentIndex < $dayEntries->count() - 1) {
                $targetIndex = $currentIndex + 1;
            }
        }

        if ($targetIndex !== null) {
            $currentItem = $dayEntries[$currentIndex];
            $sibling = $dayEntries[$targetIndex];

            $currentOrder = $currentItem->sort_order;
            $currentItem->sort_order = $sibling->sort_order;
            $sibling->sort_order = $currentOrder;

            $currentItem->saveQuietly();
            $sibling->saveQuietly();

            GcashLedgerEntry::recalculateRunningBalances();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Entry order updated. Balances recalculated.',
            ]);
        }

        return back();
    }
}
