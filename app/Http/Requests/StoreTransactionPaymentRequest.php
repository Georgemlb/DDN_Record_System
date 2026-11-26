<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof Transaction && ($this->user()?->can('collectPayment', $transaction) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Transaction $transaction */
        $transaction = $this->route('transaction');

        return [
            'amount' => ['required', 'numeric', 'gt:0', 'lte:'.max(0.01, (float) $transaction->balance)],
            'payment_method' => ['required', 'string', Rule::in(Transaction::PAYMENT_METHODS)],
            'payment_date' => ['required', 'date'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'gcash_amount' => ['nullable', 'numeric', 'min:0'],
            'gcash_tendered' => ['nullable', 'numeric', 'min:0'],
            'cash_change' => ['nullable', 'numeric', 'min:0'],
            'change_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
