<?php

namespace App\Http\Requests;

use App\Models\DailySettlement;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettlementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('view-reports') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'opening_gcash' => ['nullable', 'numeric', 'min:0'],
            'actual_cash' => ['nullable', 'numeric', 'min:0'],
            'actual_gcash' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:'.DailySettlement::STATUS_OPEN.','.DailySettlement::STATUS_CLOSED],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sync_metrics' => ['nullable', 'boolean'],
        ];
    }
}
