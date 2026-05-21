<?php

namespace App\Http\Requests;

use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-financials');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'memo'   => $this->memo !== null ? strip_tags((string) $this->memo) : null,
            'reason' => $this->reason !== null ? strip_tags((string) $this->reason) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'from_account_id' => ['required', 'integer', 'exists:bank_accounts,id', 'different:to_account_id'],
            'to_account_id'   => ['required', 'integer', 'exists:bank_accounts,id'],
            'from_project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'to_project_id'   => ['nullable', 'integer', 'exists:projects,id'],
            'date'            => ['required', 'date'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'purpose'         => ['nullable', 'string', Rule::in(array_keys(Transfer::PURPOSES))],
            'memo'            => ['nullable', 'string', 'max:255'],
            'reason'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
