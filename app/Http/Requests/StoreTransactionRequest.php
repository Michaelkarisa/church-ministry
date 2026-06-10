<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'church_id'           => ['required', 'exists:churches,id'],
            'transaction_type_id' => ['required', 'exists:transaction_types,id'],
            'amount'              => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'currency'            => ['nullable', 'string', 'size:3'],
            'transaction_date'    => ['required', 'date', 'before_or_equal:today'],
            'service_type'        => ['nullable', Rule::in(array_keys(config('church.service_types')))],
            'member_id'           => ['nullable', 'exists:members,id'],
            'reference_number'    => ['nullable', 'string', 'max:80', 'unique:transactions,reference_number'],
            'description'         => ['nullable', 'string', 'max:500'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min'           => 'Amount must be greater than 0.',
            'transaction_date.before_or_equal' => 'Transaction date cannot be in the future.',
            'reference_number.unique' => 'This reference number already exists.',
        ];
    }
}
