<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectExpense extends Model
{
    use Auditable;

    protected $fillable = [
        'project_id', 'bank_account_id', 'transfer_id', 'spent_on',
        'amount', 'description', 'vendor_ref', 'category', 'notes',
    ];

    protected $casts = [
        'spent_on'   => 'date',
        'amount'     => 'encrypted',
        'vendor_ref' => 'encrypted',
        'notes'      => 'encrypted',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function isFromTransfer(): bool
    {
        return $this->transfer_id !== null;
    }
}
