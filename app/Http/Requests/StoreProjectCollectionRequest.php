<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'reference' => $this->reference !== null ? strip_tags((string) $this->reference) : null,
            'notes'     => $this->notes !== null ? strip_tags((string) $this->notes) : null,
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
        ];
    }
}
