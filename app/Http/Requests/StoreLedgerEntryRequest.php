<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the inline "Add entry" form on an Account ledger.
 *
 * The controller currently accepts either amount_in or amount_out, never both.
 * That XOR constraint is asserted in the rules block.
 */
class StoreLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-financials');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->description !== null ? strip_tags((string) $this->description) : null,
            'notes'       => $this->notes !== null ? strip_tags((string) $this->notes) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'date'            => ['required', 'date'],
            'description'     => ['required', 'string', 'max:255'],
            'type'            => ['required', 'in:in,out'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
