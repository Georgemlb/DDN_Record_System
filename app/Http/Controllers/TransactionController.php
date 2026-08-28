<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Display recorded transactions.
     */
    public function index(): Response
    {
        return Inertia::render('transactions/Index', [
            'transactions' => Transaction::query()
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    /**
     * Show the form for recording a transaction.
     */
    public function create(): Response
    {
        return Inertia::render('transactions/Create');
    }

    /**
     * Store a newly recorded transaction.
     */
    public function store(TransactionRequest $request): RedirectResponse
    {
        $transaction = Transaction::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction recorded.']);

        return to_route('transactions.show', $transaction);
    }

    /**
     * Display a transaction.
     */
    public function show(Transaction $transaction): Response
    {
        return Inertia::render('transactions/Show', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Show the form for editing a transaction.
     */
    public function edit(Transaction $transaction): Response
    {
        return Inertia::render('transactions/Edit', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Update a recorded transaction.
     */
    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $transaction->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction updated.']);

        return to_route('transactions.show', $transaction);
    }

    /**
     * Remove a transaction.
     */
    public function destroy(Transaction $transaction): RedirectResponse
    {
        $transaction->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction deleted.']);

        return to_route('transactions.index');
    }
}
