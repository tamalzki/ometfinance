<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\LedgerEntry;
use App\Models\ProjectCollection;
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
 * Any payment recorded posts to the affected projects' outflow/inflow
 * immediately — recompute() keeps ProjectExpense rows (debit entries) and
 * ProjectCollection rows (credit entries) per voucher in sync, one row per
 * project touched, proportioned from the paid total. A voucher can span
 * multiple projects across its entries; each gets its own row. Rows are
 * removed entirely if every payment is reversed.
 *
 * Deleting a payment, deleting the voucher, or cancelling it all reverse
 * the above atomically.
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
            foreach ($voucher->attachments as $attachment) {
                $attachment->disk()->delete($attachment->path);
                $attachment->delete();
            }
            ProjectExpense::where('voucher_id', $voucher->id)->delete();
            ProjectCollection::where('voucher_id', $voucher->id)->delete();
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

        $fresh = $voucher->fresh(['payments', 'entries.category']);
        self::syncProjectOutflow($fresh);
        self::syncProjectInflow($fresh);
    }

    /**
     * Keep ProjectExpense rows in sync with what's been paid so far.
     *
     * When the voucher has accounting entries:
     *   - Each DEBIT entry with a project_id gets its own ProjectExpense row,
     *     linked via voucher_entry_id. Amount is proportioned from paid total.
     *   - All previous entry-linked expenses for this voucher are replaced.
     *
     * When no entries exist (legacy / simple voucher):
     *   - One ProjectExpense for the voucher-level project_id, amount = paid.
     *
     * Zero paid → all expenses for this voucher are removed.
     */
    private static function syncProjectOutflow(Voucher $voucher): void
    {
        $paid = $voucher->amountPaid();

        if ($paid <= 0) {
            ProjectExpense::where('voucher_id', $voucher->id)->delete();
            return;
        }

        $lastPayment = $voucher->payments->sortByDesc('paid_on')->first();
        $description = sprintf('Voucher %s — %s', $voucher->voucher_no, $voucher->payee_name);
        $spentOn     = $lastPayment?->paid_on ?? now()->toDateString();
        $bankId      = $lastPayment?->bank_account_id;

        $debitEntries = $voucher->entries
            ->where('entry_type', 'debit')
            ->whereNotNull('project_id')
            ->values();

        if ($debitEntries->isNotEmpty()) {
            // Entry-based outflow: one ProjectExpense per debit+project line.
            $totalDebit = $debitEntries->sum(fn ($e) => (float) $e->amount);

            // Delete all existing entry-linked expenses for this voucher, then recreate.
            ProjectExpense::where('voucher_id', $voucher->id)->delete();

            foreach ($debitEntries as $entry) {
                $proportion   = $totalDebit > 0 ? ((float) $entry->amount / $totalDebit) : 0;
                $entryPaidAmt = round($paid * $proportion, 2);

                if ($entryPaidAmt <= 0) {
                    continue;
                }

                ProjectExpense::create([
                    'project_id'       => $entry->project_id,
                    'voucher_id'       => $voucher->id,
                    'voucher_entry_id' => $entry->id,
                    'bank_account_id'  => $bankId,
                    'spent_on'         => $spentOn,
                    'amount'           => $entryPaidAmt,
                    'description'      => $description,
                    'vendor_ref'       => $voucher->voucher_no,
                    'category'         => $entry->category?->fullLabel() ?? $voucher->typeLabel(),
                    'category_id'      => $entry->category_id,
                ]);
            }

            return;
        }

        // Legacy / no-entries path: single expense for the voucher's project.
        if (! $voucher->project_id) {
            ProjectExpense::where('voucher_id', $voucher->id)->whereNull('voucher_entry_id')->delete();
            return;
        }

        $attrs = [
            'bank_account_id' => $bankId,
            'spent_on'        => $spentOn,
            'amount'          => $paid,
            'description'     => $description,
            'vendor_ref'      => $voucher->voucher_no,
            'category'        => $voucher->typeLabel(),
            'category_id'     => $voucher->category_id,
        ];

        $existing = ProjectExpense::where('voucher_id', $voucher->id)->whereNull('voucher_entry_id')->first();

        if ($existing) {
            $existing->update($attrs);
        } else {
            ProjectExpense::create($attrs + [
                'project_id' => $voucher->project_id,
                'voucher_id' => $voucher->id,
            ]);
        }
    }

    /**
     * Keep ProjectCollection rows in sync with what's been paid so far.
     *
     * Symmetric to syncProjectOutflow: each CREDIT entry with a project_id
     * represents money flowing INTO that project (e.g. a reimbursement or
     * inter-project settlement riding on this voucher), proportioned from
     * the paid total. Entries without a project don't post anywhere — they
     * are just the offsetting accounting side (e.g. Accounts Payable).
     *
     * Zero paid → all collections for this voucher are removed.
     */
    private static function syncProjectInflow(Voucher $voucher): void
    {
        $paid = $voucher->amountPaid();

        if ($paid <= 0) {
            ProjectCollection::where('voucher_id', $voucher->id)->delete();
            return;
        }

        $lastPayment = $voucher->payments->sortByDesc('paid_on')->first();
        $notes       = sprintf('Voucher %s — %s', $voucher->voucher_no, $voucher->payee_name);
        $collectedOn = $lastPayment?->paid_on ?? now()->toDateString();
        $bankId      = $lastPayment?->bank_account_id;

        $creditEntries = $voucher->entries
            ->where('entry_type', 'credit')
            ->whereNotNull('project_id')
            ->values();

        // Delete all existing entry-linked collections for this voucher, then recreate.
        ProjectCollection::where('voucher_id', $voucher->id)->delete();

        if ($creditEntries->isEmpty()) {
            return;
        }

        $totalCredit = $creditEntries->sum(fn ($e) => (float) $e->amount);

        foreach ($creditEntries as $entry) {
            $proportion   = $totalCredit > 0 ? ((float) $entry->amount / $totalCredit) : 0;
            $entryPaidAmt = round($paid * $proportion, 2);

            if ($entryPaidAmt <= 0) {
                continue;
            }

            ProjectCollection::create([
                'project_id'       => $entry->project_id,
                'voucher_id'       => $voucher->id,
                'voucher_entry_id' => $entry->id,
                'bank_account_id'  => $bankId,
                'collected_on'     => $collectedOn,
                'amount'           => $entryPaidAmt,
                'reference'        => $voucher->voucher_no,
                'notes'            => $notes,
            ]);
        }
    }
}
