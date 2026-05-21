<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'name', 'kind', 'code', 'client_name', 'location', 'image_path',
        'status', 'contract_value', 'start_date', 'end_date', 'due_date',
    ];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'due_date'       => 'date',
        'contract_value' => 'decimal:2',
    ];

    /* ── helpers ── */

    public function isExternal(): bool  { return $this->kind === 'external'; }
    public function isInHouse(): bool   { return $this->kind === 'in_house'; }

    public function imageUrl(): ?string
    {
        return $this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : null;
    }

    public function totalCollected(): float
    {
        // Encrypted column — aggregation handled in PHP.
        return (float) $this->collections->sum(fn ($c) => (float) $c->amount);
    }

    public function totalExpenses(): float
    {
        // Encrypted column — aggregation handled in PHP.
        return (float) $this->expenses->sum(fn ($e) => (float) $e->amount);
    }

    public function netCashPosition(): float
    {
        return $this->totalCollected() - $this->totalExpenses();
    }

    /* ── relationships ── */

    public function collections(): HasMany
    {
        return $this->hasMany(ProjectCollection::class)->orderByDesc('collected_on');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class)->orderByDesc('spent_on');
    }

    public function allocationLines(): HasMany
    {
        return $this->hasMany(ProjectAllocationLine::class)->orderBy('sort_order');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'from_project_id')->orderByDesc('date');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'to_project_id')->orderByDesc('date');
    }

    /**
     * Default “Collection Allocation” rows for external projects (matches spreadsheet template).
     * Amount for each line = collection base × percent.
     *
     * @return list<array{label: string, percent: float, row_kind: string}>
     */
    public static function defaultExternalAllocationTemplate(): array
    {
        return [
            ['label' => 'SOP', 'percent' => 0.15, 'row_kind' => ProjectAllocationLine::KIND_ALLOCATION],
            ['label' => 'Direct Costs', 'percent' => 0.3054, 'row_kind' => ProjectAllocationLine::KIND_ALLOCATION],
            ['label' => 'OCM', 'percent' => 0.05, 'row_kind' => ProjectAllocationLine::KIND_ALLOCATION],
            ['label' => 'Commission', 'percent' => 0.03, 'row_kind' => ProjectAllocationLine::KIND_ALLOCATION],
            ['label' => 'Capital Cost', 'percent' => 0.05, 'row_kind' => ProjectAllocationLine::KIND_ALLOCATION],
            ['label' => 'Admin Cost', 'percent' => 0.15, 'row_kind' => ProjectAllocationLine::KIND_ALLOCATION],
            ['label' => '', 'percent' => 0.0, 'row_kind' => ProjectAllocationLine::KIND_BLANK],
            ['label' => 'EBIT', 'percent' => 0.4545, 'row_kind' => ProjectAllocationLine::KIND_KPI],
            ['label' => 'Net Income', 'percent' => 0.31, 'row_kind' => ProjectAllocationLine::KIND_KPI],
        ];
    }

    /**
     * Populate allocation rows when none exist (e.g. new external project).
     */
    public function ensureDefaultExternalAllocationLines(): void
    {
        if (! $this->isExternal() || $this->allocationLines()->exists()) {
            return;
        }

        foreach (self::defaultExternalAllocationTemplate() as $sort => $row) {
            $this->allocationLines()->create([
                'label'    => $row['label'],
                'percent'  => $row['percent'],
                'row_kind' => $row['row_kind'],
                'sort_order' => $sort,
            ]);
        }
    }
}
