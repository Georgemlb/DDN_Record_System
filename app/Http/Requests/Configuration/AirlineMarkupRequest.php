<?php

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class AirlineMarkupRequest extends FormRequest
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
        return [
            'service_type' => ['required', 'in:flight,ferry'],
            'airline' => ['required', 'string', 'max:255'],
            'flight_type' => ['nullable', 'required_if:service_type,flight', 'in:local,international'],
            'markup_type' => ['required', 'in:fixed,percent'],
            'markup_amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'is_active' => ['boolean'],
        ];
    }
}
