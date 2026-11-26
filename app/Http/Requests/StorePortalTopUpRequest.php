<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePortalTopUpRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'entry_date' => ['required', 'date'],
            'payment_source' => ['required', 'string', 'in:cash,gcash,bank_transfer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'record_as_expense' => ['nullable', 'boolean'],
        ];
    }
}
