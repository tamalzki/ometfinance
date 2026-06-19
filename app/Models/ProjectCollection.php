<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCollection extends Model
{
    use Auditable;

    protected $fillable = [
        'project_id', 'bank_account_id', 'transfer_id', 'voucher_id', 'voucher_entry_id', 'collected_on',
        'amount', 'reference', 'notes',
    ];

    protected $casts = [
        'collected_on' => 'date',
        'amount'       => 'encrypted',
        'reference'    => 'encrypted',
        'notes'        => 'encrypted',
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

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function voucherEntry(): BelongsTo
    {
        return $this->belongsTo(VoucherEntry::class);
    }

    public function isFromTransfer(): bool
    {
        return $this->transfer_id !== null;
    }

    public function isFromVoucher(): bool
    {
        return $this->voucher_id !== null;
    }
}
