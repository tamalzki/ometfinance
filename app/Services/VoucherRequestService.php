<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRequest;
use Illuminate\Support\Facades\DB;

/**
 * Applies or discards an Accounting Staff create/edit/delete request once
 * the CFO (or admin) has reviewed it. See VoucherRequest for what each type
 * carries.
 */
class VoucherRequestService
{
    public static function approve(VoucherRequest $request, User $reviewer): void
    {
        DB::transaction(function () use ($request, $reviewer) {
            $voucher = $request->voucher;

            match ($request->type) {
                VoucherRequest::TYPE_CREATE => $voucher->update([
                    'approval_status' => 'approved',
                    'approved_by'     => $reviewer->id,
                    'approved_at'     => now(),
                ]),
                VoucherRequest::TYPE_EDIT    => self::applyEdit($voucher, $request),
                VoucherRequest::TYPE_DELETE  => VoucherService::destroyVoucher($voucher),
                VoucherRequest::TYPE_PAYMENT => self::applyPayment($voucher, $request),
                default => null,
            };

            $request->update([
                'status'      => VoucherRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
        });
    }

    public static function reject(VoucherRequest $request, User $reviewer, ?string $note): void
    {
        DB::transaction(function () use ($request, $reviewer, $note) {
            if ($request->isCreate()) {
                $request->voucher->update(['approval_status' => 'rejected']);
            }
            // Edit/delete/payment requests leave the live voucher untouched on reject —
            // a rejected payment simply never gets recorded, voucher stays unpaid.

            $request->update([
                'status'      => VoucherRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
        });
    }

    private static function applyEdit(Voucher $voucher, VoucherRequest $request): void
    {
        if ($request->payload) {
            $voucher->update($request->payload);
        }

        if ($request->entries_payload !== null) {
            $voucher->entries()->delete();
            foreach ($request->entries_payload as $i => $row) {
                $voucher->entries()->create([
                    'category_id' => $row['category_id'],
                    'entry_type'  => $row['entry_type'],
                    'amount'      => (float) $row['amount'],
                    'project_id'  => ($row['project_id'] ?? null) ?: null,
                    'description' => $row['description'] ?? null,
                    'sort_order'  => $i,
                ]);
            }
        }

        VoucherService::recompute($voucher->fresh());
    }

    private static function applyPayment(Voucher $voucher, VoucherRequest $request): void
    {
        if ($request->payload) {
            VoucherService::recordPayment($voucher, $request->payload);
        }
    }
}
