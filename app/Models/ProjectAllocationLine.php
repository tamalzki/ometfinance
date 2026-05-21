<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAllocationLine extends Model
{
    public const KIND_ALLOCATION = 'allocation';

    public const KIND_BLANK = 'blank';

    public const KIND_KPI = 'kpi';

    protected $fillable = [
        'project_id', 'row_kind', 'label', 'percent', 'sort_order',
    ];

    protected $casts = [
        'percent' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
