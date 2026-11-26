<?php

namespace App\Http\Requests\Configuration;

use App\Models\PsaRate;
use Illuminate\Foundation\Http\FormRequest;

class PsaRateRequest extends FormRequest
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
        $existing = $this->route('psa_rate');
        $uniqueRule = $existing instanceof PsaRate
            ? 'unique:psa_rates,document_type,'.$existing->id
            : 'unique:psa_rates,document_type';

        return [
            'document_type' => ['required', 'string', 'max:50', $uniqueRule],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'officer_fee' => ['required', 'numeric', 'min:0', 'lte:price'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
