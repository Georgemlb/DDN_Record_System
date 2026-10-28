<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\DailySettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GcashLedgerEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * Display a listing of recorded expenses.
     */
    public function index(): Response
    {
        $query = Expense::query()->with(['category', 'user']);

        // Status / Tab filtering
        $status = request('status', 'active');
        if ($status === 'trash') {
            $query->onlyTrashed();
        } elseif ($status === 'business') {
            $query->business();
        } elseif ($status === 'personal_pending') {
            $query->pendingReimbursement();
        } elseif ($status === 'personal_reimbursed') {
            $query->reimbursed();
        }

        // Search filtering
        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('payee', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($categoryId = request('category_id')) {
            if ($categoryId !== 'all') {
                $query->where('expense_category_id', $categoryId);
            }
        }

        // Payment method filter
        if ($paymentMethod = request('payment_method')) {
            if ($paymentMethod !== 'all') {
                $query->where('payment_method', $paymentMethod);
            }
        }

        // Date filter
        $date = request('date');
        $now = now();

        if ($date === 'today') {
            $query->whereDate('expense_date', $now->toDateString());
        } elseif ($date === 'last_7_days') {
            $query->whereBetween('expense_date', [$now->copy()->subDays(7)->toDateString(), $now->toDateString()]);
        } elseif ($date === 'this_month') {
            $query->whereMonth('expense_date', $now->month)
                ->whereYear('expense_date', $now->year);
        } elseif ($date === 'custom') {
            if ($dateFrom = request('date_from')) {
                $query->whereDate('expense_date', '>=', $dateFrom);
            }
            if ($dateTo = request('date_to')) {
                $query->whereDate('expense_date', '<=', $dateTo);
            }
        }

        // Summary Calculations (before pagination)
        $summaryQuery = clone $query;

        $topCategoryRecord = (clone $summaryQuery)
            ->selectRaw('expense_category_id, sum(amount) as total_amount')
            ->groupBy('expense_category_id')
            ->orderByDesc('total_amount')
            ->with('category')
            ->first();

        $summary = [
            'total_expenses' => (float) $summaryQuery->sum('amount'),
            'total_cash' => (float) (clone $summaryQuery)->where('payment_method', Expense::PAYMENT_METHOD_CASH)->sum('amount'),
            'total_gcash' => (float) (clone $summaryQuery)->where('payment_method', Expense::PAYMENT_METHOD_GCASH)->sum('amount'),
            'total_bank_transfer' => (float) (clone $summaryQuery)->where('payment_method', Expense::PAYMENT_METHOD_BANK_TRANSFER)->sum('amount'),
            'top_category' => $topCategoryRecord ? [
                'name' => $topCategoryRecord->category?->name ?? 'Unknown',
                'amount' => (float) $topCategoryRecord->total_amount,
            ] : null,
        ];

        // Sorting
        $sort = request('sort', 'expense_date');
        $direction = request('direction', 'desc');

        $allowedSorts = ['expense_date', 'amount', 'title'];
        if (in_array($sort, $allowedSorts, true)) {
            $query->orderBy($sort, $direction);
        }

        if ($sort !== 'id') {
            $query->orderBy('id', 'desc');
        }

        $expenses = $query->paginate(15)->withQueryString();

        $pendingPersonalQuery = Expense::query()->pendingReimbursement();

        return Inertia::render('expenses/Index', [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::query()->active()->get(),
            'filters' => request()->only(['search', 'category_id', 'payment_method', 'date', 'date_from', 'date_to', 'sort', 'direction', 'status']),
            'summary' => $summary,
            'counts' => [
                'active' => Expense::count(),
                'business' => Expense::business()->count(),
                'personal_pending' => Expense::pendingReimbursement()->count(),
                'personal_reimbursed' => Expense::reimbursed()->count(),
                'trash' => Expense::onlyTrashed()->count(),
            ],
            'pendingPersonalSummary' => [
                'count' => (clone $pendingPersonalQuery)->count(),
                'total' => (float) (clone $pendingPersonalQuery)->sum('amount'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new expense.
     */
    public function create(): Response
    {
        $duplicateId = request('duplicate_id');
        $sourceExpense = null;

        if ($duplicateId) {
            $sourceExpense = Expense::query()->with('category')->find($duplicateId);
        }

        return Inertia::render('expenses/Create', [
            'categories' => ExpenseCategory::query()->active()->get(),
            'sourceExpense' => $sourceExpense,
            'paymentMethods' => Expense::PAYMENT_METHODS,
            'recurringFrequencies' => Expense::RECURRING_FREQUENCIES,
        ]);
    }

    /**
     * Store a newly recorded expense.
     */
    public function store(ExpenseRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        if (empty($payload['user_id']) && auth()->check()) {
            $payload['user_id'] = auth()->id();
        }

        $isPersonal = ! empty($payload['is_personal']);
        $payload['is_personal'] = $isPersonal;

        if ($isPersonal) {
            $payload['reimbursement_status'] = $payload['reimbursement_status'] ?? Expense::REIMBURSEMENT_STATUS_PENDING;
        } else {
            $payload['reimbursement_status'] = Expense::REIMBURSEMENT_STATUS_NONE;
            $payload['reimbursed_at'] = null;
            $payload['reimbursement_method'] = null;
            $payload['reimbursement_notes'] = null;
        }

        $expense = Expense::query()->create($payload);

        GcashLedgerEntry::syncForExpense($expense);

        if ($expense->expense_date) {
            DailySettlement::syncForDate($expense->expense_date->toDateString());
        }
        if ($expense->reimbursed_at) {
            DailySettlement::syncForDate($expense->reimbursed_at->toDateString());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense recorded.']);

        return to_route('expenses.show', $expense);
    }

    /**
     * Display a specific expense record.
     */
    public function show(Expense $expense): Response
    {
        $expense->load(['category', 'user']);

        return Inertia::render('expenses/Show', [
            'expense' => $expense,
        ]);
    }

    /**
     * Show the form for editing an expense.
     */
    public function edit(Expense $expense): Response
    {
        Gate::authorize('update', $expense);

        $expense->load('category');

        $categories = ExpenseCategory::query()->active()->get();
        if ($expense->category && ! $expense->category->is_active) {
            $inactiveCategory = clone $expense->category;
            $inactiveCategory->name .= ' (Inactive)';
            $categories->push($inactiveCategory);
        }

        return Inertia::render('expenses/Edit', [
            'expense' => $expense,
            'categories' => $categories,
            'paymentMethods' => Expense::PAYMENT_METHODS,
            'recurringFrequencies' => Expense::RECURRING_FREQUENCIES,
        ]);
    }

    /**
     * Update a recorded expense.
     */
    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('update', $expense);

        $oldExpenseDate = $expense->expense_date?->toDateString();
        $oldReimbursedAt = $expense->reimbursed_at?->toDateString();

        $payload = $request->validated();
        $isPersonal = ! empty($payload['is_personal']);
        $payload['is_personal'] = $isPersonal;

        if ($isPersonal) {
            if (empty($payload['reimbursement_status']) || $payload['reimbursement_status'] === Expense::REIMBURSEMENT_STATUS_NONE) {
                $payload['reimbursement_status'] = Expense::REIMBURSEMENT_STATUS_PENDING;
            }
        } else {
            $payload['reimbursement_status'] = Expense::REIMBURSEMENT_STATUS_NONE;
            $payload['reimbursed_at'] = null;
            $payload['reimbursement_method'] = null;
            $payload['reimbursement_notes'] = null;
        }

        $expense->update($payload);

        GcashLedgerEntry::syncForExpense($expense);

        if ($oldExpenseDate) {
            DailySettlement::syncForDate($oldExpenseDate);
        }
        if ($expense->expense_date) {
            DailySettlement::syncForDate($expense->expense_date->toDateString());
        }
        if ($oldReimbursedAt) {
            DailySettlement::syncForDate($oldReimbursedAt);
        }
        if ($expense->reimbursed_at) {
            DailySettlement::syncForDate($expense->reimbursed_at->toDateString());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense updated.']);

        return to_route('expenses.show', $expense);
    }

    /**
     * Mark a personal expense as reimbursed.
     */
    public function reimburse(Request $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('update', $expense);

        $validated = $request->validate([
            'reimbursement_method' => ['required', 'string', Rule::in(Expense::PAYMENT_METHODS)],
            'reimbursed_at' => ['required', 'date', 'before_or_equal:today'],
            'reimbursement_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldReimbursedAt = $expense->reimbursed_at?->toDateString();

        $expense->update([
            'is_personal' => true,
            'reimbursement_status' => Expense::REIMBURSEMENT_STATUS_REIMBURSED,
            'reimbursed_at' => $validated['reimbursed_at'],
            'reimbursement_method' => $validated['reimbursement_method'],
            'reimbursement_notes' => $validated['reimbursement_notes'] ?? null,
        ]);

        GcashLedgerEntry::syncForExpense($expense);

        if ($oldReimbursedAt && $oldReimbursedAt !== $validated['reimbursed_at']) {
            DailySettlement::syncForDate($oldReimbursedAt);
        }
        DailySettlement::syncForDate($validated['reimbursed_at']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Personal expense marked as reimbursed.']);

        return back();
    }

    /**
     * Remove an expense record (move to trash).
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        $expenseDate = $expense->expense_date?->toDateString();
        $reimbursedAt = $expense->reimbursed_at?->toDateString();

        $expense->delete();

        GcashLedgerEntry::where('expense_id', $expense->id)->delete();
        GcashLedgerEntry::recalculateRunningBalances();

        if ($expenseDate) {
            DailySettlement::syncForDate($expenseDate);
        }
        if ($reimbursedAt) {
            DailySettlement::syncForDate($reimbursedAt);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense moved to trash.']);

        return to_route('expenses.index');
    }

    /**
     * Restore a soft-deleted expense.
     */
    public function restore(Expense $expense): RedirectResponse
    {
        Gate::authorize('restore', $expense);

        $expense->restore();

        GcashLedgerEntry::syncForExpense($expense);

        if ($expense->expense_date) {
            DailySettlement::syncForDate($expense->expense_date->toDateString());
        }
        if ($expense->reimbursed_at) {
            DailySettlement::syncForDate($expense->reimbursed_at->toDateString());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense restored.']);

        return back();
    }

    /**
     * Permanently remove an expense.
     */
    public function forceDelete(Expense $expense): RedirectResponse
    {
        Gate::authorize('forceDelete', $expense);

        $expenseDate = $expense->expense_date?->toDateString();
        $reimbursedAt = $expense->reimbursed_at?->toDateString();

        $expense->forceDelete();

        GcashLedgerEntry::withTrashed()->where('expense_id', $expense->id)->forceDelete();
        GcashLedgerEntry::recalculateRunningBalances();

        if ($expenseDate) {
            DailySettlement::syncForDate($expenseDate);
        }
        if ($reimbursedAt) {
            DailySettlement::syncForDate($reimbursedAt);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense permanently deleted.']);

        return back();
    }
}
