<?php

namespace App\Http\Requests;

use App\Models\PsaRate;
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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('type') === Transaction::TYPE_PSA && ! empty($this->input('psa_document_type'))) {
            $rate = PsaRate::where('document_type', $this->input('psa_document_type'))
                ->orWhere('document_type', strtoupper($this->input('psa_document_type')))
                ->first();

            if ($rate) {
                $merge = [];
                if (! $this->has('amount') || $this->input('amount') === null || $this->input('amount') === '') {
                    $merge['amount'] = $rate->price;
                }
                if (! $this->has('third_party_payment') || $this->input('third_party_payment') === null || $this->input('third_party_payment') === '') {
                    $merge['third_party_payment'] = $rate->officer_fee;
                }
                if (! empty($merge)) {
                    $this->merge($merge);
                }
            }
        }
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
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_contact' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', Rule::in(Transaction::PAYMENT_STATUSES)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'lte:amount'],
            'disbursement_source' => ['nullable', 'string', Rule::in(Transaction::DISBURSEMENT_SOURCES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->input('payment_status') === Transaction::PAYMENT_STATUS_PARTIAL) {
            $rules['amount_paid'] = ['required', 'numeric', 'gt:0', 'lt:amount'];
        }

        if ($this->input('payment_method') === Transaction::PAYMENT_METHOD_SPLIT) {
            $rules['cash_amount'] = ['required', 'numeric', 'decimal:0,2', 'min:0'];
            $rules['gcash_amount'] = ['required', 'numeric', 'decimal:0,2', 'min:0'];
        }

        if ($type === Transaction::TYPE_AIRLINE) {
            $rules['pax'] = ['required', 'integer', 'min:1'];
            $rules['trip_type'] = ['nullable', 'string', Rule::in(Transaction::TRIP_TYPES)];
            $rules['provider'] = ['nullable', 'string', 'max:255'];
            $rules['return_provider'] = ['nullable', 'string', 'max:255'];
            $rules['flight_type'] = ['nullable', 'string', Rule::in(['local', 'international'])];
            $rules['carrier_category'] = ['nullable', 'string', Rule::in(Transaction::CARRIER_CATEGORIES)];
            $rules['baggage_option_id'] = ['nullable', 'integer', 'exists:baggage_options,id'];
            $rules['service_details'] = ['nullable', 'string', 'max:1000'];
            $rules['third_party_payment'] = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'lte:amount'];
        } elseif ($type === Transaction::TYPE_FERRY) {
            $rules['pax'] = ['required', 'integer', 'min:1'];
            $rules['trip_type'] = ['nullable', 'string', Rule::in(Transaction::TRIP_TYPES)];
            $rules['provider'] = ['nullable', 'string', 'max:255'];
            $rules['service_details'] = ['nullable', 'string', 'max:1000'];
            $rules['third_party_payment'] = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'lte:amount'];
        } elseif ($type === Transaction::TYPE_TRANSLATION) {
            $rules['third_party_payment'] = ['required', 'numeric', 'decimal:0,2', 'min:0', 'lte:amount'];
        } elseif ($type === Transaction::TYPE_PSA) {
            $rules['psa_document_type'] = ['required', 'string', 'max:50'];
            $rules['third_party_payment'] = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'lte:amount'];
        } elseif ($type === Transaction::TYPE_ONLINE_ASSISTANCE) {
            $rules['online_assistance_type_id'] = ['required', 'integer', 'exists:online_assistance_types,id'];
            $rules['third_party_payment'] = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'lte:amount'];
        } elseif ($type === Transaction::TYPE_REMITTANCE) {
            $rules['service_details'] = ['required', 'string', 'max:255'];
            $rules['third_party_payment'] = ['nullable', 'numeric', 'min:0'];
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
            'cash_amount.required' => 'Please enter the cash amount for split payment.',
            'gcash_amount.required' => 'Please enter the GCash amount for split payment.',
        ];
    }
}
