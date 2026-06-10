<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherPayment extends Model
{
    use Auditable;

    protected $fillable = [
        'voucher_id', 'bank_account_id', 'ledger_entry_id',
        'paid_on', 'amount', 'mode', 'check_no', 'check_date', 'notes',
    ];

    protected $casts = [
        'paid_on'    => 'date',
        'check_date' => 'date',
        'amount'     => 'encrypted',
        'notes'      => 'encrypted',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }

    /**
     * A post-dated check is a check payment whose check date is in the future.
     */
    public function isPostDated(): bool
    {
        return $this->check_date !== null && $this->check_date->isFuture();
    }
}
