<?php

namespace App\Http\Requests\Configuration;

use App\Models\OnlineAssistanceType;
use Illuminate\Foundation\Http\FormRequest;

class OnlineAssistanceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-configurations') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $existing = $this->route('online_assistance_type');
        $uniqueRule = $existing instanceof OnlineAssistanceType
            ? 'unique:online_assistance_types,name,'.$existing->id
            : 'unique:online_assistance_types,name';

        return [
            'name' => ['required', 'string', 'max:255', $uniqueRule],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'has_disbursement' => ['nullable', 'boolean'],
            'disbursement_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
