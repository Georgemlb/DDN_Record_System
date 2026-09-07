<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\AirlineMarkup;
use App\Models\BaggageOption;
use App\Models\FlightPortalDeposit;
use App\Models\OnlineAssistanceType;
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
        $query = Transaction::query()->with(['baggageOption', 'onlineAssistanceType', 'user']);

        // Status / Trash filtering
        $status = request('status', 'active');
        if ($status === 'trash') {
            $query->onlyTrashed();
        }

        // Filtering
        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('service_details', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($type = request('type')) {
            if ($type !== 'all') {
                $query->where('type', $type);
            }
        }

        if ($paymentMethod = request('payment_method')) {
            if ($paymentMethod !== 'all') {
                $query->where('payment_method', $paymentMethod);
            }
        }

        if ($date = request('date')) {
            $now = now();
            if ($date === 'today') {
                $query->whereDate('transaction_date', $now->toDateString());
            } elseif ($date === 'last_7_days') {
                $query->whereBetween('transaction_date', [$now->copy()->subDays(7)->toDateString(), $now->toDateString()]);
            } elseif ($date === 'this_month') {
                $query->whereMonth('transaction_date', $now->month)
                    ->whereYear('transaction_date', $now->year);
            } elseif ($date === 'custom') {
                if ($dateFrom = request('date_from')) {
                    $query->whereDate('transaction_date', '>=', $dateFrom);
                }
                if ($dateTo = request('date_to')) {
                    $query->whereDate('transaction_date', '<=', $dateTo);
                }
            }
        }

        // Summary Calculations (before pagination)
        $summaryQuery = clone $query;
        $totalPayments = (float) $summaryQuery->sum('amount');
        $totalDdn = (float) $summaryQuery->sum('ddn_amount');

        $totalCashFromCash = (float) (clone $summaryQuery)->where('payment_method', Transaction::PAYMENT_METHOD_CASH)->sum('amount');
        $totalCashFromSplit = (float) (clone $summaryQuery)->where('payment_method', Transaction::PAYMENT_METHOD_SPLIT)->sum('cash_amount');
        $totalCash = $totalCashFromCash + $totalCashFromSplit;

        $totalGcashFromGcash = (float) (clone $summaryQuery)->where('payment_method', Transaction::PAYMENT_METHOD_GCASH)->sum('amount');
        $totalGcashFromSplit = (float) (clone $summaryQuery)->where('payment_method', Transaction::PAYMENT_METHOD_SPLIT)->sum('gcash_amount');
        $totalGcash = $totalGcashFromGcash + $totalGcashFromSplit;

        $summary = [
            'total_payments' => $totalPayments,
            'total_ddn' => $totalDdn,
            'total_cash' => $totalCash,
            'total_gcash' => $totalGcash,
        ];

        // Sorting
        $sort = request('sort', 'transaction_date');
        $direction = request('direction', 'desc');

        $allowedSorts = ['transaction_date', 'amount', 'ddn_amount', 'customer_name', 'reference_number'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction);
        }

        // Secondary sort to ensure stable pagination
        if ($sort !== 'id') {
            $query->orderBy('id', 'desc');
        }

        $transactions = $query->paginate(15)->withQueryString();

        return Inertia::render('transactions/Index', [
            'transactions' => $transactions,
            'filters' => request()->only(['search', 'type', 'payment_method', 'date', 'date_from', 'date_to', 'sort', 'direction', 'status']),
            'summary' => $summary,
            'counts' => [
                'active' => Transaction::count(),
                'trash' => Transaction::onlyTrashed()->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new transaction.
     */
    public function create(): Response
    {
        return Inertia::render('transactions/Create', [
            'baggageOptions' => BaggageOption::query()->active()->get(),
            'onlineAssistanceTypes' => OnlineAssistanceType::query()->active()->get(),
            'airlineMarkups' => AirlineMarkup::query()->where('is_active', true)->get(),
            'psaDocumentTypes' => Transaction::PSA_DOCUMENT_TYPES,
            'ddnSplitConfig' => config('ddn.online_assistance_split'),
            'portalBalance' => FlightPortalDeposit::getCurrentBalance(),
        ]);
    }

    /**
     * Store a newly recorded transaction.
     */
    public function store(TransactionRequest $request): RedirectResponse
    {
        $payload = $this->prepareTransactionData($request->validated());
        if (empty($payload['user_id']) && auth()->check()) {
            $payload['user_id'] = auth()->id();
        }

        $transaction = Transaction::query()->create($payload);

        FlightPortalDeposit::syncTransactionTicketDeduction($transaction);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction recorded.']);

        return to_route('transactions.show', $transaction);
    }

    /**
     * Display a transaction.
     */
    public function show(Transaction $transaction): Response
    {
        $transaction->load(['baggageOption', 'onlineAssistanceType', 'user']);

        return Inertia::render('transactions/Show', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Show the form for editing a transaction.
     */
    public function edit(Transaction $transaction): Response
    {
        $transaction->load(['baggageOption', 'onlineAssistanceType']);

        $baggageOptions = BaggageOption::query()->active()->get();
        if ($transaction->baggageOption && ! $transaction->baggageOption->is_active) {
            $inactiveOption = clone $transaction->baggageOption;
            $inactiveOption->label .= ' (Inactive)';
            $baggageOptions->push($inactiveOption);
        }

        $onlineAssistanceTypes = OnlineAssistanceType::query()->active()->get();
        if ($transaction->onlineAssistanceType && ! $transaction->onlineAssistanceType->is_active) {
            $inactiveType = clone $transaction->onlineAssistanceType;
            $inactiveType->name .= ' (Inactive)';
            $onlineAssistanceTypes->push($inactiveType);
        }

        return Inertia::render('transactions/Edit', [
            'transaction' => $transaction,
            'baggageOptions' => $baggageOptions,
            'onlineAssistanceTypes' => $onlineAssistanceTypes,
            'airlineMarkups' => AirlineMarkup::query()->where('is_active', true)->get(),
            'psaDocumentTypes' => Transaction::PSA_DOCUMENT_TYPES,
            'ddnSplitConfig' => config('ddn.online_assistance_split'),
            'portalBalance' => FlightPortalDeposit::getCurrentBalance(),
        ]);
    }

    /**
     * Update a recorded transaction.
     */
    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $payload = $this->prepareTransactionData($request->validated());

        $transaction->update($payload);

        FlightPortalDeposit::syncTransactionTicketDeduction($transaction);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction updated.']);

        return to_route('transactions.show', $transaction);
    }

    /**
     * Remove a transaction (move to trash).
     */
    public function destroy(Transaction $transaction): RedirectResponse
    {
        $transaction->delete();

        FlightPortalDeposit::removeTransactionTicketDeduction($transaction->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction moved to trash.']);

        return to_route('transactions.index');
    }

    /**
     * Restore a soft-deleted transaction.
     */
    public function restore(Transaction $transaction): RedirectResponse
    {
        $transaction->restore();

        FlightPortalDeposit::restoreTransactionTicketDeduction($transaction->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction restored.']);

        return back();
    }

    /**
     * Permanently remove a transaction.
     */
    public function forceDelete(Transaction $transaction): RedirectResponse
    {
        $transaction->forceDelete();

        FlightPortalDeposit::removeTransactionTicketDeduction($transaction->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction permanently deleted.']);

        return back();
    }

    /**
     * Prepare data and calculate financial shares based on transaction type.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareTransactionData(array $data): array
    {
        $type = $data['type'];
        $amount = (float) $data['amount'];

        // Reset type-specific fields
        $prepared = [
            'type' => $type,
            'payment_method' => $data['payment_method'],
            'transaction_date' => $data['transaction_date'],
            'customer_name' => $data['customer_name'],
            'amount' => $amount,
            'cash_amount' => $data['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT ? (float) ($data['cash_amount'] ?? 0) : null,
            'gcash_amount' => $data['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT ? (float) ($data['gcash_amount'] ?? 0) : null,
            'notes' => $data['notes'] ?? null,
            'pax' => null,
            'provider' => null,
            'flight_type' => null,
            'service_details' => null,
            'baggage_option_id' => null,
            'psa_document_type' => null,
            'online_assistance_type_id' => null,
            'third_party_payment' => null,
            'ddn_amount' => null,
            'employee_share' => null,
        ];

        switch ($type) {
            case Transaction::TYPE_AIRLINE:
                $thirdParty = (float) ($data['third_party_payment'] ?? 0);
                $prepared['pax'] = (int) $data['pax'];
                $prepared['provider'] = $data['provider'] ?? null;
                $prepared['flight_type'] = $data['flight_type'] ?? null;
                $prepared['baggage_option_id'] = ! empty($data['baggage_option_id']) ? (int) $data['baggage_option_id'] : null;
                $prepared['service_details'] = $data['service_details'] ?? null;
                $prepared['third_party_payment'] = $thirdParty > 0 ? $thirdParty : null;
                $prepared['ddn_amount'] = round(max(0, $amount - $thirdParty), 2);
                break;

            case Transaction::TYPE_TRANSLATION:
                $thirdParty = (float) ($data['third_party_payment'] ?? 0);
                $prepared['third_party_payment'] = $thirdParty;
                $prepared['ddn_amount'] = round(max(0, $amount - $thirdParty), 2);
                break;

            case Transaction::TYPE_PSA:
                $thirdParty = (float) ($data['third_party_payment'] ?? 0);
                $prepared['psa_document_type'] = $data['psa_document_type'];
                $prepared['third_party_payment'] = $thirdParty;
                $prepared['ddn_amount'] = round(max(0, $amount - $thirdParty), 2);
                break;

            case Transaction::TYPE_ONLINE_ASSISTANCE:
                $split = config('ddn.online_assistance_split', ['ddn' => 0.60, 'employee' => 0.40]);
                $prepared['online_assistance_type_id'] = (int) $data['online_assistance_type_id'];
                $prepared['ddn_amount'] = round($amount * $split['ddn'], 2);
                $prepared['employee_share'] = round($amount * $split['employee'], 2);
                break;

            case Transaction::TYPE_PRINTING:
                $prepared['ddn_amount'] = $amount;
                break;
        }

        return $prepared;
    }
}
