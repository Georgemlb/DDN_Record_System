<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'expense_category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')->where('is_active', true),
            ],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', 'string', Rule::in(Expense::PAYMENT_METHODS)],
            'payee' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'recurring_frequency' => ['nullable', 'string', Rule::in(Expense::RECURRING_FREQUENCIES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Please provide an expense title or description.',
            'expense_category_id.required' => 'Please select an expense category.',
            'expense_category_id.exists' => 'The selected expense category is invalid or inactive.',
            'amount.required' => 'Please enter the expense amount.',
            'amount.gt' => 'Amount must be greater than zero.',
            'amount.decimal' => 'Amount may have at most two decimal places.',
            'expense_date.required' => 'Please provide the expense date.',
            'expense_date.before_or_equal' => 'Expense date cannot be in the future.',
            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in' => 'Selected payment method is invalid.',
        ];
    }
}
