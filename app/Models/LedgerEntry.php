<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    use Auditable;

    protected $fillable = [
        'bank_account_id',
        'transfer_id',
        'date',
        'description',
        'amount_out',
        'amount_in',
        'notes',
    ];

    protected $casts = [
        'date'        => 'date',
        'amount_out'  => 'encrypted',
        'amount_in'   => 'encrypted',
        'notes'       => 'encrypted',
        'description' => 'encrypted',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function isTransfer(): bool
    {
        return $this->transfer_id !== null;
    }
}
