<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\LedgerEntry;
use App\Models\Project;
use App\Models\ProjectCollection;
use App\Models\ProjectExpense;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;

/**
 * Central money-movement engine.
 *
 * Every intercompany transfer flows through this service so that:
 *   • Bank ledgers (both sides) stay in sync via paired LedgerEntry rows.
 *   • Project books stay in sync via ProjectExpense / ProjectCollection
 *     when a transfer is tagged to a project.
 *   • Updates replace downstream rows atomically; delete removes everything.
 */
class TransferService
{
    /**
     * @param array{
     *   from_account_id:int,
     *   to_account_id:int,
     *   date:string,
     *   amount:float|string,
     *   memo?:string|null,
     *   purpose?:string|null,
     *   reason?:string|null,
     *   from_project_id?:int|null,
     *   to_project_id?:int|null,
     * } $data
     */
    public static function create(array $data): Transfer
    {
        return DB::transaction(function () use ($data) {
            $fromAccount = BankAccount::with('entity')->findOrFail($data['from_account_id']);
            $toAccount   = BankAccount::with('entity')->findOrFail($data['to_account_id']);
            $fromProject = ! empty($data['from_project_id']) ? Project::find($data['from_project_id']) : null;
            $toProject   = ! empty($data['to_project_id']) ? Project::find($data['to_project_id']) : null;

            $transfer = Transfer::create([
                'from_account_id' => $fromAccount->id,
                'to_account_id'   => $toAccount->id,
                'from_project_id' => $fromProject?->id,
                'to_project_id'   => $toProject?->id,
                'date'            => $data['date'],
                'amount'          => $data['amount'],
                'memo'            => $data['memo'] ?? null,
                'purpose'         => $data['purpose'] ?? null,
                'reason'          => $data['reason'] ?? null,
            ]);

            self::writeLedgerLegs($transfer, $fromAccount, $toAccount);
            self::writeProjectSides($transfer, $fromAccount, $toAccount, $fromProject, $toProject);

            return $transfer->fresh([
                'fromAccount.entity', 'toAccount.entity',
                'fromProject', 'toProject',
                'ledgerEntries', 'projectInflow', 'projectOutflow',
            ]);
        });
    }

    /**
     * @param array<string, mixed> $data same shape as create()
     */
    public static function update(Transfer $transfer, array $data): Transfer
    {
        return DB::transaction(function () use ($transfer, $data) {
            ProjectCollection::where('transfer_id', $transfer->id)->delete();
            ProjectExpense::where('transfer_id', $transfer->id)->delete();
            LedgerEntry::where('transfer_id', $transfer->id)->delete();

            $fromAccount = BankAccount::with('entity')->findOrFail($data['from_account_id']);
            $toAccount   = BankAccount::with('entity')->findOrFail($data['to_account_id']);
            $fromProject = ! empty($data['from_project_id']) ? Project::find($data['from_project_id']) : null;
            $toProject   = ! empty($data['to_project_id']) ? Project::find($data['to_project_id']) : null;

            $transfer->update([
                'from_account_id' => $fromAccount->id,
                'to_account_id'     => $toAccount->id,
                'from_project_id'   => $fromProject?->id,
                'to_project_id'     => $toProject?->id,
                'date'              => $data['date'],
                'amount'            => $data['amount'],
                'memo'              => $data['memo'] ?? null,
                'purpose'           => $data['purpose'] ?? null,
                'reason'            => $data['reason'] ?? null,
            ]);
            $transfer->refresh();

            self::writeLedgerLegs($transfer, $fromAccount, $toAccount);
            self::writeProjectSides($transfer, $fromAccount, $toAccount, $fromProject, $toProject);

            return $transfer->fresh([
                'fromAccount.entity', 'toAccount.entity',
                'fromProject', 'toProject',
                'ledgerEntries', 'projectInflow', 'projectOutflow',
            ]);
        });
    }

    public static function destroy(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            ProjectCollection::where('transfer_id', $transfer->id)->delete();
            ProjectExpense::where('transfer_id', $transfer->id)->delete();
            LedgerEntry::where('transfer_id', $transfer->id)->delete();
            $transfer->delete();
        });
    }

    private static function writeLedgerLegs(Transfer $transfer, BankAccount $fromAccount, BankAccount $toAccount): void
    {
        $memo   = trim((string) ($transfer->memo ?? ''));
        $suffix = $memo !== '' ? ' — ' . $memo : '';

        LedgerEntry::create([
            'bank_account_id' => $fromAccount->id,
            'transfer_id'     => $transfer->id,
            'date'            => $transfer->date,
            'description'     => "Transfer to {$toAccount->name}{$suffix}",
            'amount_out'      => $transfer->amount,
        ]);
        LedgerEntry::create([
            'bank_account_id' => $toAccount->id,
            'transfer_id'     => $transfer->id,
            'date'            => $transfer->date,
            'description'     => "Transfer from {$fromAccount->name}{$suffix}",
            'amount_in'       => $transfer->amount,
        ]);
    }

    private static function writeProjectSides(
        Transfer $transfer,
        BankAccount $fromAccount,
        BankAccount $toAccount,
        ?Project $fromProject,
        ?Project $toProject
    ): void {
        $purposeLabel = $transfer->purposeLabel();
        $memo         = trim((string) ($transfer->memo ?? ''));

        if ($fromProject) {
            ProjectExpense::create([
                'project_id'      => $fromProject->id,
                'bank_account_id' => $fromAccount->id,
                'transfer_id'     => $transfer->id,
                'spent_on'        => $transfer->date,
                'amount'          => $transfer->amount,
                'category'        => $purposeLabel,
                'description'     => sprintf(
                    'Transfer to %s (%s)',
                    $toAccount->name,
                    $toProject ? $toProject->name : $toAccount->entity?->name
                ),
                'vendor_ref'      => 'TRF-' . $transfer->id,
                'notes'           => trim($transfer->reason ?? $memo) ?: null,
            ]);
        }

        if ($toProject) {
            ProjectCollection::create([
                'project_id'      => $toProject->id,
                'bank_account_id' => $toAccount->id,
                'transfer_id'     => $transfer->id,
                'collected_on'    => $transfer->date,
                'amount'          => $transfer->amount,
                'reference'       => 'TRF-' . $transfer->id,
                'notes'           => sprintf(
                    '%s from %s%s%s',
                    $purposeLabel,
                    $fromAccount->name,
                    $fromProject ? ' (' . $fromProject->name . ')' : '',
                    $transfer->reason ? ' — ' . $transfer->reason : ''
                ),
            ]);
        }
    }
}
