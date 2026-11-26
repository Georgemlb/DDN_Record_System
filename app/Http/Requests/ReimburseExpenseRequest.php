<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReimburseExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense && ($this->user()?->can('update', $expense) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reimbursement_method' => ['required', 'string', Rule::in(Expense::PAYMENT_METHODS)],
            'reimbursed_at' => ['required', 'date', 'before_or_equal:today'],
            'reimbursement_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
