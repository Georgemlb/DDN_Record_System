<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = $this->input('type', Transaction::TYPE_AIRLINE);

        $rules = [
            'type' => ['required', 'string', Rule::in(Transaction::TYPES)],
            'payment_method' => ['required', 'string', Rule::in(Transaction::PAYMENT_METHODS)],
            'transaction_date' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($type === Transaction::TYPE_AIRLINE) {
            $rules['pax'] = ['required', 'integer', 'min:1'];
            $rules['provider'] = ['nullable', 'string', 'max:255'];
            $rules['baggage_option_id'] = ['nullable', 'integer', 'exists:baggage_options,id'];
            $rules['service_details'] = ['nullable', 'string', 'max:1000'];
        } elseif ($type === Transaction::TYPE_TRANSLATION) {
            $rules['third_party_payment'] = ['required', 'numeric', 'decimal:0,2', 'min:0', 'lte:amount'];
        } elseif ($type === Transaction::TYPE_PSA) {
            $rules['psa_document_type'] = ['required', 'string', Rule::in(Transaction::PSA_DOCUMENT_TYPES)];
            $rules['third_party_payment'] = ['required', 'numeric', 'decimal:0,2', 'min:0', 'lte:amount'];
        } elseif ($type === Transaction::TYPE_ONLINE_ASSISTANCE) {
            $rules['online_assistance_type_id'] = ['required', 'integer', 'exists:online_assistance_types,id'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'third_party_payment.lte' => 'Third-party payment cannot exceed the total amount.',
            'third_party_payment.required' => 'Please enter the payment amount.',
            'psa_document_type.required' => 'Please select a PSA document type.',
            'online_assistance_type_id.required' => 'Please select an online assistance type.',
        ];
    }
}
