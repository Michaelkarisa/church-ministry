<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $txnId = $this->route('transaction');

        return [
            'transaction_type_id' => ['sometimes', 'exists:transaction_types,id'],
            'amount'              => ['sometimes', 'numeric', 'min:0.01', 'max:999999999'],
            'currency'            => ['nullable', 'string', 'size:3'],
            'transaction_date'    => ['sometimes', 'date', 'before_or_equal:today'],
            'service_type'        => ['nullable', Rule::in(array_keys(config('church.service_types')))],
            'member_id'           => ['nullable', 'exists:members,id'],
            'reference_number'    => ['nullable', 'string', 'max:80', Rule::unique('transactions', 'reference_number')->ignore($txnId)],
            'description'         => ['nullable', 'string', 'max:500'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ];
    }
}
