<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a "Record inflow / funding" submission.
 *
 * description-like fields are tag-stripped in prepareForValidation so any
 * raw HTML in pasted memos can't survive into the database.
 */
class StoreProjectCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-financials');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference'               => $this->reference !== null ? strip_tags((string) $this->reference) : null,
            'notes'                   => $this->notes !== null ? strip_tags((string) $this->notes) : null,
            'other_deductions_notes'  => $this->other_deductions_notes !== null ? strip_tags((string) $this->other_deductions_notes) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'collected_on'    => ['required', 'date'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'reference'       => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:1000'],

            // Deductions — rates auto-suggested in the UI from client/transaction
            // type, but the actual amounts deducted are computed server-side
            // from whatever rate is submitted so the stored figures can't drift.
            'client_type'             => ['nullable', 'string', Rule::in(['government', 'private'])],
            'transaction_type'        => ['nullable', 'string', Rule::in(['goods', 'services'])],
            'vat_rate'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'wht_rate'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'retention_rate'          => ['nullable', 'numeric', 'min:0', 'max:100'],
            'recoupment_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'other_deductions_amount' => ['nullable', 'numeric', 'min:0'],
            'other_deductions_notes'  => ['nullable', 'string', 'max:500'],
        ];
    }
}
