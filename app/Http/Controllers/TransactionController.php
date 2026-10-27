<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\AirlineMarkup;
use App\Models\Customer;
use App\Models\DailySettlement;
use App\Models\FlightPortalDeposit;
use App\Models\GcashLedgerEntry;
use App\Models\OnlineAssistanceType;
use App\Models\PsaRate;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Display recorded transactions.
     */
    public function index(): Response
    {
        $query = Transaction::query()->with(['onlineAssistanceType', 'user', 'customer']);

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
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('contact_number', 'like', "%{$search}%");
                    });
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

        if ($paymentStatus = request('payment_status')) {
            if ($paymentStatus !== 'all') {
                $query->where('payment_status', $paymentStatus);
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
        $totalBalance = (float) $summaryQuery->sum('balance');

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
            'total_balance' => $totalBalance,
        ];

        // Sorting
        $sort = request('sort', 'transaction_date');
        $direction = request('direction', 'desc');

        $allowedSorts = ['transaction_date', 'amount', 'ddn_amount', 'customer_name', 'reference_number', 'balance'];
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
            'filters' => request()->only(['search', 'type', 'payment_method', 'payment_status', 'date', 'date_from', 'date_to', 'sort', 'direction', 'status']),
            'summary' => $summary,
            'counts' => [
                'active' => Transaction::count(),
                'trash' => Transaction::onlyTrashed()->count(),
                'unpaid_or_partial' => Transaction::whereIn('payment_status', [Transaction::PAYMENT_STATUS_PARTIAL, Transaction::PAYMENT_STATUS_UNPAID])->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new transaction.
     */
    public function create(): Response
    {
        return Inertia::render('transactions/Create', [
            'recurringCustomers' => Customer::query()->orderBy('name')->get(['id', 'name', 'contact_number']),
            'initialCustomers' => Customer::query()->orderBy('name')->get(['id', 'name', 'contact_number']),
            'onlineAssistanceTypes' => OnlineAssistanceType::query()->active()->get(),
            'airlineMarkups' => AirlineMarkup::query()->where('is_active', true)->get(),
            'psaDocumentTypes' => Transaction::PSA_DOCUMENT_TYPES,
            'psaRates' => PsaRate::query()->active()->get(),
            'ddnSplitConfig' => config('ddn.online_assistance_split'),
            'portalBalance' => FlightPortalDeposit::getCurrentBalance(),
        ]);
    }

    /**
     * Store a newly recorded transaction.
     */
    public function store(TransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Auto-link or create Customer
        if (empty($validated['customer_id']) && ! empty($validated['customer_name'])) {
            $customer = Customer::firstOrCreate(
                ['name' => trim($validated['customer_name'])],
                ['contact_number' => $request->input('customer_contact')]
            );
            $validated['customer_id'] = $customer->id;
        } elseif (! empty($validated['customer_id']) && ! empty($request->input('customer_contact'))) {
            $customer = Customer::find($validated['customer_id']);
            if ($customer && empty($customer->contact_number)) {
                $customer->update(['contact_number' => $request->input('customer_contact')]);
            }
        }

        $payload = $this->prepareTransactionData($validated);
        if (empty($payload['user_id']) && auth()->check()) {
            $payload['user_id'] = auth()->id();
        }

        $transaction = Transaction::query()->create($payload);

        // Record initial payment if amount_paid > 0 and not already recorded
        if ($payload['amount_paid'] > 0 && $transaction->payments()->doesntExist()) {
            $transaction->payments()->create([
                'user_id' => auth()->id(),
                'amount' => $payload['amount_paid'],
                'payment_method' => $payload['payment_method'],
                'cash_amount' => $payload['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT ? $payload['cash_amount'] : null,
                'gcash_amount' => $payload['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT ? $payload['gcash_amount'] : null,
                'gcash_tendered' => $payload['gcash_tendered'] ?? null,
                'cash_change' => $payload['cash_change'] ?? 0,
                'change_fee' => $payload['change_fee'] ?? 0,
                'payment_date' => $payload['transaction_date'],
                'notes' => $payload['payment_status'] === Transaction::PAYMENT_STATUS_PARTIAL ? 'Initial downpayment' : 'Full payment',
            ]);
        }

        FlightPortalDeposit::syncTransactionTicketDeduction($transaction);
        GcashLedgerEntry::syncForTransaction($transaction);
        DailySettlement::syncForDate($transaction->transaction_date->toDateString());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction recorded.']);

        return to_route('transactions.show', $transaction);
    }

    /**
     * Display a transaction.
     */
    public function show(Transaction $transaction): Response
    {
        $transaction->load(['onlineAssistanceType', 'user', 'customer', 'payments.user']);

        return Inertia::render('transactions/Show', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Show the form for editing a transaction.
     */
    public function edit(Transaction $transaction): Response
    {
        Gate::authorize('update', $transaction);

        $transaction->load(['onlineAssistanceType', 'customer']);

        $onlineAssistanceTypes = OnlineAssistanceType::query()->active()->get();
        if ($transaction->onlineAssistanceType && ! $transaction->onlineAssistanceType->is_active) {
            $inactiveType = clone $transaction->onlineAssistanceType;
            $inactiveType->name .= ' (Inactive)';
            $onlineAssistanceTypes->push($inactiveType);
        }

        return Inertia::render('transactions/Edit', [
            'transaction' => $transaction,
            'recurringCustomers' => Customer::query()->orderBy('name')->get(['id', 'name', 'contact_number']),
            'initialCustomers' => Customer::query()->orderBy('name')->get(['id', 'name', 'contact_number']),
            'onlineAssistanceTypes' => $onlineAssistanceTypes,
            'airlineMarkups' => AirlineMarkup::query()->where('is_active', true)->get(),
            'psaDocumentTypes' => Transaction::PSA_DOCUMENT_TYPES,
            'psaRates' => PsaRate::query()->orderBy('sort_order')->orderBy('name')->get(),
            'ddnSplitConfig' => config('ddn.online_assistance_split'),
            'portalBalance' => FlightPortalDeposit::getCurrentBalance(),
        ]);
    }

    /**
     * Update a recorded transaction.
     */
    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        $validated = $request->validated();

        if (empty($validated['customer_id']) && ! empty($validated['customer_name'])) {
            $customer = Customer::firstOrCreate(
                ['name' => trim($validated['customer_name'])],
                ['contact_number' => $request->input('customer_contact')]
            );
            $validated['customer_id'] = $customer->id;
        } elseif (! empty($validated['customer_id']) && ! empty($request->input('customer_contact'))) {
            $customer = Customer::find($validated['customer_id']);
            if ($customer && empty($customer->contact_number)) {
                $customer->update(['contact_number' => $request->input('customer_contact')]);
            }
        }

        $payload = $this->prepareTransactionData($validated);
        $oldDate = $transaction->transaction_date?->toDateString();

        $transaction->update($payload);

        // Synchronize initial payment when transaction has 0 or 1 payment record
        $paymentsCount = $transaction->payments()->count();
        if ($paymentsCount <= 1) {
            $payment = $transaction->payments()->first();
            $amountPaid = (float) ($payload['amount_paid'] ?? 0);

            if ($amountPaid > 0) {
                if (! $payment) {
                    $transaction->payments()->create([
                        'user_id' => $transaction->user_id ?? auth()->id(),
                        'amount' => $amountPaid,
                        'payment_method' => $payload['payment_method'],
                        'cash_amount' => $payload['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT ? $payload['cash_amount'] : null,
                        'gcash_amount' => $payload['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT ? $payload['gcash_amount'] : null,
                        'gcash_tendered' => $payload['gcash_tendered'] ?? null,
                        'cash_change' => $payload['cash_change'] ?? 0,
                        'change_fee' => $payload['change_fee'] ?? 0,
                        'payment_date' => $payload['transaction_date'],
                        'notes' => $payload['payment_status'] === Transaction::PAYMENT_STATUS_PARTIAL ? 'Initial downpayment' : 'Full payment',
                    ]);
                } else {
                    $oldPaymentDate = $payment->payment_date?->toDateString();
                    $payment->update([
                        'amount' => $amountPaid,
                        'payment_method' => $payload['payment_method'],
                        'cash_amount' => $payload['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT ? $payload['cash_amount'] : null,
                        'gcash_amount' => $payload['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT ? $payload['gcash_amount'] : null,
                        'gcash_tendered' => $payload['gcash_tendered'] ?? null,
                        'cash_change' => $payload['cash_change'] ?? 0,
                        'change_fee' => $payload['change_fee'] ?? 0,
                        'payment_date' => $payload['transaction_date'],
                    ]);

                    if ($oldPaymentDate && $oldPaymentDate !== $transaction->transaction_date?->toDateString()) {
                        DailySettlement::syncForDate($oldPaymentDate);
                    }
                }
            } elseif ($payment) {
                $oldPaymentDate = $payment->payment_date?->toDateString();
                $payment->delete();
                if ($oldPaymentDate) {
                    DailySettlement::syncForDate($oldPaymentDate);
                }
            }
        }

        $transaction->recalculateBalance();

        FlightPortalDeposit::syncTransactionTicketDeduction($transaction);
        GcashLedgerEntry::syncForTransaction($transaction);

        if ($oldDate && $oldDate !== $transaction->transaction_date?->toDateString()) {
            DailySettlement::syncForDate($oldDate);
        }
        DailySettlement::syncForDate($transaction->transaction_date->toDateString());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction updated.']);

        return to_route('transactions.show', $transaction);
    }

    /**
     * Record a balance collection payment for a transaction.
     */
    public function storePayment(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'lte:'.max(0.01, (float) $transaction->balance)],
            'payment_method' => ['required', 'string', Rule::in(Transaction::PAYMENT_METHODS)],
            'payment_date' => ['required', 'date'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'gcash_amount' => ['nullable', 'numeric', 'min:0'],
            'gcash_tendered' => ['nullable', 'numeric', 'min:0'],
            'cash_change' => ['nullable', 'numeric', 'min:0'],
            'change_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $amount = (float) $validated['amount'];

        if ($validated['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT) {
            $cash = (float) ($validated['cash_amount'] ?? 0);
            $gcash = (float) ($validated['gcash_amount'] ?? 0);
            if (round($cash + $gcash, 2) !== round($amount, 2)) {
                return back()->withErrors(['amount' => 'Cash + GCash amounts must equal total payment amount.']);
            }
        }

        $transaction->payments()->create([
            'user_id' => auth()->id(),
            'amount' => $amount,
            'payment_method' => $validated['payment_method'],
            'cash_amount' => $validated['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT ? (float) ($validated['cash_amount'] ?? 0) : null,
            'gcash_amount' => $validated['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT ? (float) ($validated['gcash_amount'] ?? 0) : null,
            'gcash_tendered' => ! empty($validated['gcash_tendered']) ? (float) $validated['gcash_tendered'] : null,
            'cash_change' => ! empty($validated['cash_change']) ? (float) $validated['cash_change'] : 0,
            'change_fee' => ! empty($validated['change_fee']) ? (float) $validated['change_fee'] : 0,
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'] ?? 'Balance collection on pickup',
        ]);

        $transaction->recalculateBalance();
        GcashLedgerEntry::syncForTransaction($transaction);
        DailySettlement::syncForDate($validated['payment_date']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Payment of ₱'.number_format($amount, 2).' recorded.',
        ]);

        return back();
    }

    /**
     * Remove a transaction (move to trash).
     */
    public function destroy(Transaction $transaction): RedirectResponse
    {
        Gate::authorize('delete', $transaction);

        $affectedDates = $transaction->payments()->pluck('payment_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $affectedDates[] = $transaction->transaction_date->toDateString();

        $transaction->delete();

        FlightPortalDeposit::removeTransactionTicketDeduction($transaction->id);
        GcashLedgerEntry::removeForTransaction($transaction->id);

        foreach (array_unique($affectedDates) as $date) {
            DailySettlement::syncForDate($date);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction moved to trash.']);

        return to_route('transactions.index');
    }

    /**
     * Restore a soft-deleted transaction.
     */
    public function restore(Transaction $transaction): RedirectResponse
    {
        Gate::authorize('restore', $transaction);

        $affectedDates = $transaction->payments()->pluck('payment_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $affectedDates[] = $transaction->transaction_date->toDateString();

        $transaction->restore();

        FlightPortalDeposit::restoreTransactionTicketDeduction($transaction->id);
        GcashLedgerEntry::restoreForTransaction($transaction->id);

        foreach (array_unique($affectedDates) as $date) {
            DailySettlement::syncForDate($date);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transaction restored.']);

        return back();
    }

    /**
     * Permanently remove a transaction.
     */
    public function forceDelete(Transaction $transaction): RedirectResponse
    {
        Gate::authorize('forceDelete', $transaction);

        $affectedDates = $transaction->payments()->pluck('payment_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $affectedDates[] = $transaction->transaction_date->toDateString();

        $transaction->payments()->delete();
        $transaction->forceDelete();

        FlightPortalDeposit::removeTransactionTicketDeduction($transaction->id);
        GcashLedgerEntry::removeForTransaction($transaction->id);

        foreach (array_unique($affectedDates) as $date) {
            DailySettlement::syncForDate($date);
        }

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
        $status = $data['payment_status'] ?? Transaction::PAYMENT_STATUS_PAID;

        if ($status === Transaction::PAYMENT_STATUS_PAID) {
            $amountPaid = $amount;
            $balance = 0.00;
        } elseif ($status === Transaction::PAYMENT_STATUS_UNPAID) {
            $amountPaid = 0.00;
            $balance = $amount;
        } else {
            $amountPaid = (float) ($data['amount_paid'] ?? 0);
            $balance = max(0, round($amount - $amountPaid, 2));
            if ($balance <= 0) {
                $status = Transaction::PAYMENT_STATUS_PAID;
            }
        }

        // Reset type-specific fields
        $prepared = [
            'type' => $type,
            'customer_id' => ! empty($data['customer_id']) ? (int) $data['customer_id'] : null,
            'payment_method' => $data['payment_method'],
            'payment_status' => $status,
            'transaction_date' => $data['transaction_date'],
            'customer_name' => $data['customer_name'],
            'amount' => $amount,
            'amount_paid' => $amountPaid,
            'balance' => $balance,
            'cash_amount' => $data['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT ? (float) ($data['cash_amount'] ?? 0) : null,
            'gcash_amount' => $data['payment_method'] === Transaction::PAYMENT_METHOD_SPLIT ? (float) ($data['gcash_amount'] ?? 0) : null,
            'gcash_tendered' => ! empty($data['gcash_tendered']) ? (float) $data['gcash_tendered'] : null,
            'cash_change' => ! empty($data['cash_change']) ? (float) $data['cash_change'] : 0.00,
            'change_fee' => ! empty($data['change_fee']) ? (float) $data['change_fee'] : 0.00,
            'notes' => $data['notes'] ?? null,
            'pax' => null,
            'copies' => null,
            'trip_type' => null,
            'provider' => null,
            'return_provider' => null,
            'flight_type' => null,
            'carrier_category' => null,
            'service_details' => null,
            'psa_document_type' => null,
            'online_assistance_type_id' => null,
            'third_party_payment' => null,
            'disbursement_source' => $data['disbursement_source'] ?? null,
            'ddn_amount' => null,
            'employee_share' => null,
        ];

        switch ($type) {
            case Transaction::TYPE_AIRLINE:
                $thirdParty = (float) ($data['third_party_payment'] ?? 0);
                $prepared['pax'] = (int) $data['pax'];
                $prepared['trip_type'] = $data['trip_type'] ?? Transaction::TRIP_TYPE_ONE_WAY;
                $isRoundTrip = ($data['trip_type'] ?? null) === Transaction::TRIP_TYPE_ROUND_TRIP;
                $prepared['provider'] = $data['provider'] ?? null;
                $prepared['return_provider'] = $isRoundTrip ? ($data['return_provider'] ?? null) : null;
                $prepared['flight_type'] = $data['flight_type'] ?? null;
                $prepared['carrier_category'] = $data['carrier_category'] ?? null;
                $prepared['service_details'] = $data['service_details'] ?? null;
                $prepared['third_party_payment'] = $thirdParty > 0 ? $thirdParty : null;
                $prepared['disbursement_source'] = $data['disbursement_source'] ?? Transaction::DISBURSEMENT_SOURCE_FLIGHT_PORTAL;
                $prepared['ddn_amount'] = round(max(0, $amount - $thirdParty), 2);
                break;

            case Transaction::TYPE_FERRY:
                $thirdParty = (float) ($data['third_party_payment'] ?? 0);
                $prepared['pax'] = (int) $data['pax'];
                $prepared['trip_type'] = $data['trip_type'] ?? Transaction::TRIP_TYPE_ONE_WAY;
                $prepared['provider'] = $data['provider'] ?? 'All Ferries';
                $prepared['service_details'] = $data['service_details'] ?? null;
                $prepared['third_party_payment'] = $thirdParty > 0 ? $thirdParty : null;
                $prepared['disbursement_source'] = $data['disbursement_source'] ?? Transaction::DISBURSEMENT_SOURCE_FLIGHT_PORTAL;
                $prepared['ddn_amount'] = round(max(0, $amount - $thirdParty), 2);
                break;

            case Transaction::TYPE_TRANSLATION:
                $thirdParty = (float) ($data['third_party_payment'] ?? 0);
                $prepared['third_party_payment'] = $thirdParty;
                $prepared['disbursement_source'] = $data['disbursement_source'] ?? Transaction::DISBURSEMENT_SOURCE_CASH;
                $prepared['ddn_amount'] = round(max(0, $amount - $thirdParty), 2);
                break;

            case Transaction::TYPE_PSA:
                $copies = max(1, (int) ($data['copies'] ?? 1));
                $rate = PsaRate::where('document_type', $data['psa_document_type'])
                    ->orWhere('document_type', strtoupper($data['psa_document_type']))
                    ->first();
                if ($rate) {
                    $amount = round((float) $rate->price * $copies, 2);
                    $thirdParty = round((float) $rate->officer_fee * $copies, 2);
                    $prepared['amount'] = $amount;
                    if ($status === Transaction::PAYMENT_STATUS_PAID) {
                        $prepared['amount_paid'] = $amount;
                        $prepared['balance'] = 0.00;
                    } elseif ($status === Transaction::PAYMENT_STATUS_UNPAID) {
                        $prepared['amount_paid'] = 0.00;
                        $prepared['balance'] = $amount;
                    } else {
                        $prepared['amount_paid'] = min($amount, (float) ($data['amount_paid'] ?? 0));
                        $prepared['balance'] = max(0, round($amount - $prepared['amount_paid'], 2));
                    }
                } else {
                    $thirdParty = (float) ($data['third_party_payment'] ?? 0);
                }
                $prepared['psa_document_type'] = $data['psa_document_type'];
                $prepared['copies'] = $copies;
                $prepared['third_party_payment'] = $thirdParty;
                $prepared['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_CASH;
                $prepared['ddn_amount'] = round(max(0, $amount - $thirdParty), 2);
                break;

            case Transaction::TYPE_ONLINE_ASSISTANCE:
                $assistanceType = ! empty($data['online_assistance_type_id'])
                    ? OnlineAssistanceType::find($data['online_assistance_type_id'])
                    : null;
                $hasDisbursement = $assistanceType ? (bool) $assistanceType->has_disbursement : true;

                if (! $hasDisbursement) {
                    $thirdParty = 0.0;
                } elseif (isset($data['third_party_payment']) && $data['third_party_payment'] !== '') {
                    $thirdParty = (float) $data['third_party_payment'];
                } elseif ($assistanceType && $assistanceType->disbursement_amount !== null) {
                    $thirdParty = (float) $assistanceType->disbursement_amount;
                } else {
                    $thirdParty = 0.0;
                }

                $margin = max(0, $amount - $thirdParty);
                $split = config('ddn.online_assistance_split', ['ddn' => 0.60, 'employee' => 0.40]);
                $prepared['online_assistance_type_id'] = (int) $data['online_assistance_type_id'];
                $prepared['third_party_payment'] = $thirdParty > 0 ? $thirdParty : null;
                $prepared['disbursement_source'] = $hasDisbursement
                    ? ($data['disbursement_source'] ?? ($thirdParty > 0 ? Transaction::DISBURSEMENT_SOURCE_GCASH : Transaction::DISBURSEMENT_SOURCE_NONE))
                    : Transaction::DISBURSEMENT_SOURCE_NONE;
                $prepared['ddn_amount'] = round($margin * $split['ddn'], 2);
                $prepared['employee_share'] = round($margin * $split['employee'], 2);
                break;

            case Transaction::TYPE_PRINTING:
                $prepared['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_NONE;
                $prepared['ddn_amount'] = $amount;
                break;

            case Transaction::TYPE_REMITTANCE:
                $prepared['service_details'] = $data['service_details'] ?? 'GCASH_CASH_OUT';
                $fee = (float) ($data['third_party_payment'] ?? 0);
                $prepared['third_party_payment'] = null;
                $prepared['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_NONE;
                $prepared['ddn_amount'] = $fee;
                break;
        }

        return $prepared;
    }
}
