<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-financials');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->description !== null ? strip_tags((string) $this->description) : null,
            'vendor_ref'  => $this->vendor_ref !== null ? strip_tags((string) $this->vendor_ref) : null,
            'notes'       => $this->notes !== null ? strip_tags((string) $this->notes) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'spent_on'        => ['required', 'date'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'description'     => ['nullable', 'string', 'max:255'],
            'vendor_ref'      => ['nullable', 'string', 'max:255'],
            'category'        => ['nullable', 'string', 'max:60'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }
}
