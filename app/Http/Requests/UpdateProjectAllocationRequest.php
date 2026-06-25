<?php

namespace App\Http\Requests;

use App\Models\ProjectAllocationLine;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an "Adjust allocation" submission — new percentages for an
 * external project's budget allocation buckets, keyed by allocation line id.
 */
class UpdateProjectAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-financials');
    }

    public function rules(): array
    {
        return [
            'percents'   => ['required', 'array'],
            'percents.*' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * "Allocated" is always totalCollected × percent (it's never a stored
     * amount — see Project::totalCollected()), so the bucket percents can
     * never legitimately add up to more than 100%: that would allocate money
     * that hasn't actually been collected yet.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $project = $this->route('project');
            $percents = $this->input('percents', []);

            $bucketIds = $project?->allocationLines
                ->where('row_kind', ProjectAllocationLine::KIND_ALLOCATION)
                ->pluck('id');

            if (! $bucketIds) {
                return;
            }

            $sum = 0.0;
            foreach ($percents as $lineId => $percent) {
                if ($bucketIds->contains((int) $lineId)) {
                    $sum += (float) $percent;
                }
            }

            if ($sum > 100.01) {
                $validator->errors()->add(
                    'percents',
                    'Allocation buckets add up to ' . number_format($sum, 2) . '% — that\'s more than the 100% of collected funds available. Reduce one or more buckets first.'
                );
            }
        });
    }
}
