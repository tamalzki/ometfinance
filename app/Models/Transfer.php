<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transfer extends Model
{
    use Auditable;

    /** Canonical purpose codes used across the central transfer register. */
    public const PURPOSES = [
        'intercompany'      => 'Intercompany Funding',
        'project_funding'   => 'Project Funding',
        'reimbursement'     => 'Reimbursement',
        'capital_injection' => 'Capital Injection',
        'expense_payment'   => 'Expense Payment',
        'sweep'             => 'Sweep / Consolidation',
        'other'             => 'Other',
    ];

    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'from_project_id',
        'to_project_id',
        'date',
        'amount',
        'memo',
        'purpose',
        'reason',
    ];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'encrypted',
        'memo'   => 'encrypted',
        'reason' => 'encrypted',
    ];

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'to_account_id');
    }

    public function fromProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'from_project_id');
    }

    public function toProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'to_project_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function projectInflow(): HasOne
    {
        return $this->hasOne(ProjectCollection::class);
    }

    public function projectOutflow(): HasOne
    {
        return $this->hasOne(ProjectExpense::class);
    }

    public function purposeLabel(): string
    {
        return self::PURPOSES[$this->purpose] ?? ($this->purpose ? ucfirst(str_replace('_', ' ', $this->purpose)) : 'Unclassified');
    }

    /**
     * True when source and destination belong to different entities.
     */
    public function isIntercompany(): bool
    {
        $from = $this->fromAccount?->entity_id;
        $to   = $this->toAccount?->entity_id;
        return $from && $to && $from !== $to;
    }

    public function hasProjectImpact(): bool
    {
        return $this->from_project_id !== null || $this->to_project_id !== null;
    }
}
