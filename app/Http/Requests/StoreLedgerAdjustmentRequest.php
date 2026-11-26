<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLedgerAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-flight-portal') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2'],
            'entry_date' => ['required', 'date'],
            'notes' => ['required', 'string', 'max:1000'],
            'is_balance_anchor' => ['nullable', 'boolean'],
            'running_balance' => ['nullable', 'numeric', 'decimal:0,2'],
        ];
    }
}
