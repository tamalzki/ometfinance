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
        'client_type', 'transaction_type',
        'vat_rate', 'vat_amount',
        'wht_rate', 'wht_amount',
        'retention_rate', 'retention_amount',
        'recoupment_rate', 'recoupment_amount',
        'other_deductions_amount', 'other_deductions_notes',
    ];

    protected $casts = [
        'collected_on'             => 'date',
        'amount'                   => 'encrypted',
        'reference'                => 'encrypted',
        'notes'                    => 'encrypted',
        'vat_rate'                 => 'decimal:2',
        'vat_amount'               => 'encrypted',
        'wht_rate'                 => 'decimal:2',
        'wht_amount'               => 'encrypted',
        'retention_rate'           => 'decimal:2',
        'retention_amount'         => 'encrypted',
        'recoupment_rate'          => 'decimal:2',
        'recoupment_amount'        => 'encrypted',
        'other_deductions_amount'  => 'encrypted',
        'other_deductions_notes'   => 'encrypted',
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

    /**
     * Sum of every deduction taken out of the gross amount: VAT, withholding
     * tax, retention, recoupment, and any other agency-specific deduction.
     */
    public function totalDeductions(): float
    {
        return (float) $this->vat_amount
            + (float) $this->wht_amount
            + (float) $this->retention_amount
            + (float) $this->recoupment_amount
            + (float) $this->other_deductions_amount;
    }

    /**
     * The actual cash collected after statutory/contractual deductions —
     * what we call the "real" collection.
     */
    public function netAmount(): float
    {
        return (float) $this->amount - $this->totalDeductions();
    }
}
