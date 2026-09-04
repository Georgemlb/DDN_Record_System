<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        ExpenseCategory::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense category created successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'expense-categories']);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name,' . $expenseCategory->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        $expenseCategory->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense category updated successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'expense-categories']);
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        if ($expenseCategory->expenses()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot delete expense category because it is used in existing expenses. Deactivate it instead.',
            ]);

            return redirect()->route('configurations.index', ['tab' => 'expense-categories'])
                ->with('error', 'Cannot delete expense category because it is used in existing expenses. Deactivate it instead.');
        }

        $expenseCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense category deleted successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'expense-categories']);
    }
}
