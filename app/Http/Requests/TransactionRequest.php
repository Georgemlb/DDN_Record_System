<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:255'],
            'pax' => ['required', 'integer', 'min:1'],
            'provider' => ['nullable', 'string', 'max:255'],
            'service_details' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
