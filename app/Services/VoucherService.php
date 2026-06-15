<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\LedgerEntry;
use App\Models\ProjectExpense;
use App\Models\Voucher;
use App\Models\VoucherPayment;
use Illuminate\Support\Facades\DB;

/**
 * Disbursement engine for vouchers.
 *
 * Recording a payment:
 *   • Posts a LedgerEntry (amount_out) on the source bank account — this
 *     deducts the account because BankAccount::currentBalance() is ledger-derived.
 *   • Recomputes the voucher's paid total, status and release date.
 *
 * Any payment recorded posts to the project's outflow immediately —
 * recompute() keeps a single running ProjectExpense per voucher in sync
 * (amount = total paid so far), tagged Partial or Full via the voucher's
 * status. The entry is removed entirely if every payment is reversed.
 *
 * Deleting a payment reverses all of the above atomically.
 */
class VoucherService
{
    /**
     * Generate the next voucher number for a year, format `YYYY-####`.
     */
    public static function nextVoucherNo(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        $latest = Voucher::withTrashed()
            ->where('voucher_no', 'like', $year . '-%')
            ->orderByDesc('voucher_no')
            ->value('voucher_no');

        $seq = 0;
        if ($latest && preg_match('/-(\d+)$/', $latest, $m)) {
            $seq = (int) $m[1];
        }

        return sprintf('%d-%04d', $year, $seq + 1);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function recordPayment(Voucher $voucher, array $data): VoucherPayment
    {
        return DB::transaction(function () use ($voucher, $data) {
            $account = ! empty($data['bank_account_id'])
                ? BankAccount::with('entity')->find($data['bank_account_id'])
                : null;

            $paidOn = $data['paid_on'] ?? now()->toDateString();
            $amount = (float) $data['amount'];
            $mode   = $data['mode'] ?? $voucher->mode_of_payment;

            $ledgerEntry = null;
            if ($account) {
                $ledgerEntry = LedgerEntry::create([
                    'bank_account_id' => $account->id,
                    'date'            => $paidOn,
                    'description'     => sprintf('Voucher %s — %s', $voucher->voucher_no, $voucher->payee_name),
                    'amount_out'      => $amount,
                    'notes'           => $data['notes'] ?? null,
                ]);
            }

            $payment = VoucherPayment::create([
                'voucher_id'      => $voucher->id,
                'bank_account_id' => $account?->id,
                'ledger_entry_id' => $ledgerEntry?->id,
                'paid_on'         => $paidOn,
                'amount'          => $amount,
                'mode'            => $mode,
                'check_no'        => $data['check_no'] ?? null,
                'check_date'      => $data['check_date'] ?? null,
                'notes'           => $data['notes'] ?? null,
            ]);

            self::recompute($voucher);

            return $payment->fresh(['bankAccount', 'voucher']);
        });
    }

    public static function deletePayment(VoucherPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $voucher = $payment->voucher;

            $payment->ledgerEntry?->delete();
            $payment->delete();

            if ($voucher) {
                self::recompute($voucher->fresh());
            }
        });
    }

    public static function destroyVoucher(Voucher $voucher): void
    {
        DB::transaction(function () use ($voucher) {
            foreach ($voucher->payments as $payment) {
                $payment->ledgerEntry?->delete();
                $payment->delete();
            }
            $voucher->projectExpense?->delete();
            $voucher->delete();
        });
    }

    /**
     * Recompute paid total, status (unless cancelled) and release date from
     * the voucher's payments.
     */
    public static function recompute(Voucher $voucher): void
    {
        $voucher->load('payments');

        $paid    = $voucher->amountPaid();
        $payable = (float) $voucher->amount_payable;
        $hasPdc  = $voucher->payments->contains(fn ($p) => $p->isPostDated());

        $status = $voucher->status;
        if ($status !== 'cancelled') {
            if ($paid <= 0) {
                $status = $hasPdc ? 'pdc' : 'unpaid';
            } elseif ($paid + 0.01 < $payable) {
                $status = $hasPdc ? 'pdc' : 'partial';
            } else {
                $status = 'paid';
            }
        }

        $lastPaidOn = $voucher->payments
            ->filter(fn ($p) => ! $p->isPostDated())
            ->max('paid_on');

        $voucher->update([
            'status'       => $status,
            'release_date' => $status === 'paid' ? ($lastPaidOn ?? $voucher->release_date) : $voucher->release_date,
        ]);

        self::syncProjectOutflow($voucher->fresh(['payments', 'projectExpense']));
    }

    /**
     * Keep the voucher's running ProjectExpense in step with what's been
     * paid so far. Nothing is owed out yet (Unpaid, zero paid) → no entry.
     * Once any payment lands, the entry exists with amount = total paid;
     * its tag (Partial vs Full) is read from the voucher's own status.
     */
    private static function syncProjectOutflow(Voucher $voucher): void
    {
        $paid = $voucher->amountPaid();

        if ($paid <= 0 || ! $voucher->project_id) {
            $voucher->projectExpense?->delete();
            return;
        }

        $lastPayment = $voucher->payments->sortByDesc('paid_on')->first();

        $attrs = [
            'bank_account_id' => $lastPayment?->bank_account_id,
            'spent_on'        => $lastPayment?->paid_on ?? now()->toDateString(),
            'amount'          => $paid,
            'description'     => sprintf('Voucher %s — %s', $voucher->voucher_no, $voucher->payee_name),
            'vendor_ref'      => $voucher->voucher_no,
            'category'        => $voucher->typeLabel(),
            'category_id'     => $voucher->category_id,
        ];

        if ($voucher->projectExpense) {
            $voucher->projectExpense->update($attrs);
        } else {
            ProjectExpense::create($attrs + [
                'project_id' => $voucher->project_id,
                'voucher_id' => $voucher->id,
            ]);
        }
    }
}
