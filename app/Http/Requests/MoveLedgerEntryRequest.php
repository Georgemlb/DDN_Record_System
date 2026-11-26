<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveLedgerEntryRequest extends FormRequest
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
            'direction' => ['required', 'string', 'in:up,down'],
            'table_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
