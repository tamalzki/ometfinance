<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a "Fund from another account" submission on a project page.
 *
 * Funding is a Transfer under the hood (TransferService), so both bank
 * ledgers and the project book stay in sync. `from_project_id` marks the
 * inflow as support from another project rather than a plain borrowing.
 */
class StoreProjectFundingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-financials');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->notes !== null ? strip_tags((string) $this->notes) : null,
        ]);
    }

    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'from_account_id' => ['required', 'integer', 'exists:bank_accounts,id', 'different:to_account_id'],
            'to_account_id'   => ['required', 'integer', 'exists:bank_accounts,id'],
            'from_project_id' => [
                'nullable',
                'integer',
                'exists:projects,id',
                Rule::notIn([$project?->id]),
            ],
            'date'            => ['required', 'date'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_account_id.required'  => 'Pick the account the money is coming from.',
            'from_account_id.different' => 'Source and destination accounts must be different.',
            'to_account_id.required'    => 'Pick the account the money is deposited to.',
            'from_project_id.not_in'    => 'A project cannot fund itself — pick a different source project.',
        ];
    }
}
