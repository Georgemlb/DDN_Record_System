<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ExpenseCategoryController extends Controller
{
    /**
     * Store a newly created category.
     */
    public function store(ExpenseCategoryRequest $request): JsonResponse|RedirectResponse
    {
        $maxSortOrder = (int) ExpenseCategory::query()->max('sort_order');

        $category = ExpenseCategory::query()->create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'is_active' => true,
            'sort_order' => $maxSortOrder + 1,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'category' => $category,
                'message' => 'Category created successfully.',
            ], 201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense category added.']);
        Inertia::flash('new_category', $category);

        return back()->with('new_category', $category);
    }
}
