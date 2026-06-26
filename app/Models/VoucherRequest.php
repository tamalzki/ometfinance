<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending create/edit/delete request submitted by Accounting Staff,
 * awaiting CFO (or admin) review.
 *
 * - type=create: $voucher already exists with approval_status='pending';
 *   this row carries no payload — the voucher row itself is the proposal.
 * - type=edit: $voucher is a live, approved voucher; payload/entries_payload
 *   hold the proposed values, applied only on approval.
 * - type=delete: no payload — just a reason for the deletion request.
 */
class VoucherRequest extends Model
{
    public const TYPE_CREATE = 'create';
    public const TYPE_EDIT = 'edit';
    public const TYPE_DELETE = 'delete';
    public const TYPE_PAYMENT = 'payment';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'voucher_id', 'type', 'status', 'requested_by', 'reason',
        'payload', 'entries_payload', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'payload'         => 'array',
        'entries_payload' => 'array',
        'reviewed_at'     => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }
    public function isCreate(): bool   { return $this->type === self::TYPE_CREATE; }
    public function isEdit(): bool     { return $this->type === self::TYPE_EDIT; }
    public function isDelete(): bool   { return $this->type === self::TYPE_DELETE; }
    public function isPayment(): bool  { return $this->type === self::TYPE_PAYMENT; }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_CREATE  => 'For Approval',
            self::TYPE_EDIT    => 'Edit Request',
            self::TYPE_DELETE  => 'Delete Request',
            self::TYPE_PAYMENT => 'Payment Verification',
            default            => ucfirst($this->type),
        };
    }

    /**
     * Field-level diff for the "Changed fields only" table — compares the
     * proposed payload against the voucher's current (approved) values.
     * Money/date fields are pre-formatted for display.
     *
     * @return list<array{key: string, label: string, before: string, after: string}>
     */
    /** @return array<string,string> */
    private static function fieldLabels(): array
    {
        return [
            'payee_name'             => 'Payee',
            'amount_payable'         => 'Amount Payable',
            'particular'             => 'Particular',
            'due_date'               => 'Due Date',
            'voucher_date'           => 'Voucher Date',
            'release_date'           => 'Release Date',
            'mode_of_payment'        => 'Mode of Payment',
            'reference'              => 'Reference',
            'notes'                  => 'Notes',
            'project_id'             => 'Project',
            'source_bank_account_id' => 'Source Bank Account',
            'category_id'            => 'Category',
        ];
    }

    private static function formatFieldValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if ($key === 'amount_payable') {
            return '₱' . number_format((float) $value, 2);
        }
        if (in_array($key, ['due_date', 'voucher_date', 'release_date'], true)) {
            return \Illuminate\Support\Carbon::parse($value)->format('M j, Y');
        }
        return (string) $value;
    }

    public function changedFields(): array
    {
        if (! $this->isEdit() || ! $this->payload) {
            return [];
        }

        $labels = self::fieldLabels();

        $diff = [];
        foreach ($this->payload as $key => $newValue) {
            if (! array_key_exists($key, $labels)) {
                continue;
            }
            $oldValue = $this->voucher?->getAttribute($key);
            $oldComparable = $oldValue instanceof \Illuminate\Support\Carbon ? $oldValue->toDateString() : $oldValue;

            if ((string) $oldComparable === (string) $newValue) {
                continue;
            }

            $diff[] = [
                'key'    => $key,
                'label'  => $labels[$key],
                'before' => self::formatFieldValue($key, $oldComparable),
                'after'  => self::formatFieldValue($key, $newValue),
            ];
        }

        return $diff;
    }

    /**
     * The flip side of changedFields() — fields that were submitted but
     * match the voucher's current value, for the "Unchanged fields"
     * collapsible on the review screen.
     *
     * @return list<array{label: string, value: string}>
     */
    public function unchangedFields(): array
    {
        if (! $this->isEdit() || ! $this->payload) {
            return [];
        }

        $labels = self::fieldLabels();
        $changedKeys = array_column($this->changedFields(), 'key');

        $unchanged = [];
        foreach ($this->payload as $key => $value) {
            if (! array_key_exists($key, $labels) || in_array($key, $changedKeys, true)) {
                continue;
            }
            $unchanged[] = ['label' => $labels[$key], 'value' => self::formatFieldValue($key, $value)];
        }

        return $unchanged;
    }

    /**
     * Accounting-entries diff for the review screen — classifies each
     * proposed entry as unchanged, modified, or newly added relative to the
     * voucher's current entries (matched by id when present).
     *
     * @return array{rows: list<array<string,mixed>>, totalDebitBefore: float, totalDebitAfter: float, totalCreditBefore: float, totalCreditAfter: float}
     */
    public function entriesDiff(): array
    {
        $current  = $this->voucher?->entries ?? collect();
        $proposed = collect($this->entries_payload ?? []);

        $categories = ProjectCategory::whereIn('id', $proposed->pluck('category_id')->filter())->get()->keyBy('id');
        $projects   = Project::whereIn('id', $proposed->pluck('project_id')->filter())->get()->keyBy('id');

        $rows = [];
        $matchedCurrentIds = [];

        foreach ($proposed as $row) {
            $row['category_label'] = $categories->get($row['category_id'] ?? null)?->fullLabel();
            $row['project_name']   = $projects->get($row['project_id'] ?? null)?->name;

            $existing = ! empty($row['id']) ? $current->firstWhere('id', $row['id']) : null;

            if ($existing) {
                $matchedCurrentIds[] = $existing->id;
                $changed = (float) $existing->amount !== (float) ($row['amount'] ?? 0)
                    || (int) $existing->category_id !== (int) ($row['category_id'] ?? 0)
                    || $existing->entry_type !== ($row['entry_type'] ?? null)
                    || (string) $existing->description !== (string) ($row['description'] ?? '');

                $rows[] = [
                    'state'    => $changed ? 'modified' : 'unchanged',
                    'before'   => $existing,
                    'after'    => $row,
                ];
            } else {
                $rows[] = ['state' => 'added', 'before' => null, 'after' => $row];
            }
        }

        foreach ($current as $entry) {
            if (! in_array($entry->id, $matchedCurrentIds, true)) {
                $rows[] = ['state' => 'removed', 'before' => $entry, 'after' => null];
            }
        }

        return [
            'rows'              => $rows,
            'totalDebitBefore'  => (float) $current->where('entry_type', 'debit')->sum('amount'),
            'totalDebitAfter'   => (float) $proposed->where('entry_type', 'debit')->sum('amount'),
            'totalCreditBefore' => (float) $current->where('entry_type', 'credit')->sum('amount'),
            'totalCreditAfter'  => (float) $proposed->where('entry_type', 'credit')->sum('amount'),
        ];
    }
}
