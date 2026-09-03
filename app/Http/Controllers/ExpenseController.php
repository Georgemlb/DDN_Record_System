<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * Display a listing of recorded expenses.
     */
    public function index(): Response
    {
        $query = Expense::query()->with('category');

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

        return Inertia::render('expenses/Index', [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::query()->active()->get(),
            'filters' => request()->only(['search', 'category_id', 'payment_method', 'date', 'date_from', 'date_to', 'sort', 'direction']),
            'summary' => $summary,
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
        $expense = Expense::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense recorded.']);

        return to_route('expenses.show', $expense);
    }

    /**
     * Display a specific expense record.
     */
    public function show(Expense $expense): Response
    {
        $expense->load('category');

        return Inertia::render('expenses/Show', [
            'expense' => $expense,
        ]);
    }

    /**
     * Show the form for editing an expense.
     */
    public function edit(Expense $expense): Response
    {
        $expense->load('category');

        $categories = ExpenseCategory::query()->active()->get();
        if ($expense->category && !$expense->category->is_active) {
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
        $expense->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense updated.']);

        return to_route('expenses.show', $expense);
    }

    /**
     * Soft-delete an expense record.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense deleted.']);

        return to_route('expenses.index');
    }
}
