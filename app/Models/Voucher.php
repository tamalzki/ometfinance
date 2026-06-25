<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;


class Voucher extends Model
{
    use Auditable;
    use SoftDeletes;

    /** Canonical status set — mirrors the spreadsheet's Status column. */
    public const STATUSES = [
        'draft'     => 'Draft',
        'unpaid'    => 'Unpaid',
        'partial'   => 'Partially Paid',
        'pdc'       => 'PDC',
        'paid'      => 'Paid',
        'cancelled' => 'Cancelled',
    ];

    /** Disbursement transaction types (from the misnamed "PO Number" column). */
    public const TYPES = [
        'rfp'          => 'Request for Payment',
        'payroll'      => 'Payroll',
        'encashment'   => 'Encashment',
        'replenishment'=> 'Replenishment',
        'transfer'     => 'Fund Transfer',
        'rent'         => 'Rent',
        'prof_fee'     => 'Professional Fee',
        'reimbursement'=> 'Reimbursement',
        'cash_advance' => 'Cash Advance',
        'collection'   => 'Collection',
        'other'        => 'Other',
    ];

    /** Office location / source of the voucher. */
    public const SOURCES = [
        'mindanao' => 'Main',
        'bgc'      => 'BGC',
    ];

    /** The originating paper/document that triggered this voucher — tagging only. */
    public const SOURCE_DOCUMENTS = [
        'rfp'            => 'Request For Payment',
        'purchase_order' => 'Purchase Order',
        'travel_request' => 'Travel Request Form',
        'contract'       => 'Contract',
    ];

    /** Lucide icon name per source-document type — shared by the picker UI and table badges. */
    public const SOURCE_DOCUMENT_ICONS = [
        'rfp'            => 'wallet',
        'purchase_order' => 'shopping-cart',
        'travel_request' => 'plane',
        'contract'       => 'file-signature',
    ];

    /** Label for the reference-number field that appears once a source document is picked (stored in po_number). */
    public const SOURCE_DOCUMENT_NUMBER_LABELS = [
        'rfp'            => 'RFP Number',
        'purchase_order' => 'PO Number',
        'travel_request' => 'TRF Number',
        'contract'       => 'Contract Number',
    ];

    /** Modes of payment seen across the ledger. */
    public const MODES = [
        'cash'           => 'Cash',
        'check'          => 'Check',
        'fund_transfer'  => 'Fund Transfer',
        'gcash'          => 'Gcash',
        'autodebit'      => 'Autodebit',
        'cash_deposit'   => 'Cash Deposit',
        'lddap_ada'      => 'LDDAP-ADA',
        'managers_check' => "Manager's Check",
        'other'          => 'Other',
    ];

    protected $fillable = [
        'voucher_no', 'voucher_date', 'due_date', 'release_date',
        'payee_name', 'source', 'project_id', 'source_bank_account_id',
        'transaction_type', 'source_document_type', 'category_id', 'po_number', 'reference', 'amount_payable',
        'mode_of_payment', 'status', 'approval_status', 'particular', 'notes',
        'remarks', 'source_of_fund', 'or_ref', 'change_amount',
        'prepared_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'voucher_date'   => 'date',
        'due_date'       => 'date',
        'release_date'   => 'date',
        'amount_payable'  => 'encrypted',
        'po_number'       => 'encrypted',
        'reference'       => 'encrypted',
        'particular'      => 'encrypted',
        'notes'           => 'encrypted',
        'remarks'         => 'encrypted',
        'source_of_fund'  => 'encrypted',
        'or_ref'          => 'encrypted',
        'change_amount'   => 'decimal:2',
        'approved_at'     => 'datetime',
    ];

    /* ── relationships ─────────────────────────────────────────────────── */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sourceBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'source_bank_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'category_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Job-title label for the signature block — e.g. "CFO". */
    public function approverPositionLabel(): ?string
    {
        return $this->approvedBy?->positionLabel();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VoucherPayment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(VoucherAttachment::class)->latest();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(VoucherEntry::class)->orderBy('sort_order')->orderBy('id');
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(VoucherRequest::class)->latest();
    }

    /** The single edit/delete request awaiting CFO review, if any. */
    public function pendingRequest(): ?VoucherRequest
    {
        return $this->relationLoaded('approvalRequests')
            ? $this->approvalRequests->firstWhere('status', VoucherRequest::STATUS_PENDING)
            : $this->approvalRequests()->where('status', VoucherRequest::STATUS_PENDING)->first();
    }

    /** Most recently submitted request of any status — used to surface a fresh CFO decision. */
    public function latestRequest(): ?VoucherRequest
    {
        return $this->relationLoaded('approvalRequests')
            ? $this->approvalRequests->first()
            : $this->approvalRequests()->first();
    }

    /** True when the most recent CFO decision on this voucher was a rejection (and nothing newer supersedes it). */
    public function hasFreshRejection(): bool
    {
        $latest = $this->latestRequest();
        return $latest !== null && $latest->status === VoucherRequest::STATUS_REJECTED;
    }

    /**
     * All project outflow rows this voucher has posted (one per debit entry
     * with a project when accounting entries are used; otherwise one row for
     * the voucher-level project).
     */
    public function projectExpenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class);
    }

    /** Legacy accessor — first expense row for vouchers without entries. */
    public function projectExpense(): HasOne
    {
        return $this->hasOne(ProjectExpense::class)->whereNull('voucher_entry_id');
    }

    /* ── computed money helpers (encrypted → summed in PHP) ────────────── */

    public function amountPaid(): float
    {
        return (float) $this->payments->sum(fn ($p) => (float) $p->amount);
    }

    public function balanceDue(): float
    {
        return max(0.0, (float) $this->amount_payable - $this->amountPaid());
    }

    /* ── status / aging helpers ────────────────────────────────────────── */

    public function isOpen(): bool
    {
        return in_array($this->status, ['unpaid', 'partial', 'pdc'], true);
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function isApprovalRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    public function approvalStatusLabel(): string
    {
        return match ($this->approval_status) {
            'pending'  => 'For Approval',
            'rejected' => 'Rejected',
            default    => 'Approved',
        };
    }

    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->due_date !== null
            && $this->due_date->endOfDay()->isPast()
            && $this->balanceDue() > 0;
    }

    public function daysUntilDue(): ?int
    {
        if (! $this->due_date) {
            return null;
        }
        return Carbon::today()->diffInDays($this->due_date->startOfDay(), false);
    }

    /**
     * Aging bucket key for the payables report. Returns one of:
     * pdc | no_term | current | d1_30 | d31_60 | d60_plus
     */
    public function agingBucket(): string
    {
        if ($this->status === 'pdc') {
            return 'pdc';
        }
        if (! $this->due_date) {
            return 'no_term';
        }
        $days = $this->daysUntilDue();
        if ($days >= 0) {
            return 'current';
        }
        $overdue = abs($days);
        if ($overdue <= 30) {
            return 'd1_30';
        }
        if ($overdue <= 60) {
            return 'd31_60';
        }
        return 'd60_plus';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->transaction_type] ?? ($this->transaction_type ? ucfirst($this->transaction_type) : '—');
    }

    public function sourceDocumentLabel(): string
    {
        return self::SOURCE_DOCUMENTS[$this->source_document_type] ?? ($this->source_document_type ? ucfirst($this->source_document_type) : '—');
    }

    public function sourceDocumentIcon(): string
    {
        return self::SOURCE_DOCUMENT_ICONS[$this->source_document_type] ?? 'file-question';
    }

    public function modeLabel(): string
    {
        return self::MODES[$this->mode_of_payment] ?? ($this->mode_of_payment ? ucfirst($this->mode_of_payment) : '—');
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ($this->source ? ucfirst($this->source) : '—');
    }
}
