<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Models\BankAccount;
use App\Models\Entity;
use App\Models\LedgerEntry;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AccountController extends Controller
{
    /* ───────────────────────────────────────────────────────────────────────
     |  Read views
     ────────────────────────────────────────────────────────────────────── */

    public function index(): RedirectResponse
    {
        return redirect()->route('accounts.overall');
    }

    public function overall(Request $request): View
    {
        $entities = Entity::orderBy('sort_order')
            ->with('bankAccounts')
            ->withCount('bankAccounts')
            ->get();

        foreach ($entities as $entity) {
            foreach ($entity->bankAccounts as $account) {
                $account->computed_balance = $account->currentBalance();
            }
            $entity->computed_total = (float) $entity->bankAccounts->sum('computed_balance');
        }

        $totalCashInBank = (float) $entities->sum('computed_total');
        $allAccounts     = BankAccount::with('entity')->orderBy('name')->get();

        /* ── Optional: load a specific account's ledger ── */
        $activeAccount = null;
        $entries       = collect();
        $from          = null;
        $to            = null;

        $accountId = $request->query('account_id');
        if ($accountId) {
            $activeAccount = BankAccount::with('entity')->find($accountId);

            if ($activeAccount) {
                $from = $request->query('from');
                $to   = $request->query('to');

                $allRows = LedgerEntry::where('bank_account_id', $activeAccount->id)
                    ->orderBy('date')
                    ->orderBy('id')
                    ->get();

                $running = (float) $activeAccount->opening_balance;
                $allRows = $allRows->map(function ($entry) use (&$running) {
                    $running += ($entry->amount_in ?? 0) - ($entry->amount_out ?? 0);
                    $entry->running_balance = $running;
                    return $entry;
                });

                $entries = $allRows->filter(function ($e) use ($from, $to) {
                    if ($from && $e->date->lt(Carbon::parse($from)->startOfDay())) {
                        return false;
                    }
                    if ($to && $e->date->gt(Carbon::parse($to)->endOfDay())) {
                        return false;
                    }
                    return true;
                })->values();
            }
        }

        return view('accounts.overall', compact(
            'entities', 'totalCashInBank', 'allAccounts',
            'activeAccount', 'entries', 'from', 'to'
        ));
    }

    /** Legacy deep-link — redirect to the unified page. */
    public function entity(string $entitySlug): RedirectResponse
    {
        return redirect()->route('accounts.overall');
    }

    /** Legacy deep-link — redirect to unified page with account pre-selected. */
    public function show(Request $request, string $entitySlug, int $accountId): RedirectResponse
    {
        $params = array_filter([
            'account_id' => $accountId,
            'from'       => $request->query('from'),
            'to'         => $request->query('to'),
        ]);

        return redirect()->route('accounts.overall', $params);
    }

    /* ───────────────────────────────────────────────────────────────────────
     |  Create
     ────────────────────────────────────────────────────────────────────── */

    public function storeEntry(\App\Http\Requests\StoreLedgerEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        LedgerEntry::create([
            'bank_account_id' => $validated['bank_account_id'],
            'date'            => $validated['date'],
            'description'     => $validated['description'],
            'amount_in'       => $validated['type'] === 'in'  ? $validated['amount'] : null,
            'amount_out'      => $validated['type'] === 'out' ? $validated['amount'] : null,
            'notes'           => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('accounts.overall', ['account_id' => $validated['bank_account_id']])
            ->with('success', 'Entry added successfully.');
    }

    public function storeTransfer(StoreTransferRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        TransferService::create($validated);

        return redirect()
            ->route('accounts.overall', ['account_id' => $validated['from_account_id']])
            ->with('success', 'Transfer completed. Both accounts have been updated.');
    }

    public function storeBankAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entity_id'       => ['required', 'exists:entities,id'],
            'name'            => ['required', 'string', 'max:255'],
            'bank_name'       => ['required', 'string', 'max:255'],
            'account_number'  => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'numeric'],
        ]);

        $bankAccount = BankAccount::create([
            'entity_id'       => $validated['entity_id'],
            'name'            => $validated['name'],
            'bank_name'       => $validated['bank_name'],
            'account_number'  => $validated['account_number'] ?? null,
            'opening_balance' => $validated['opening_balance'] ?? 0,
        ]);

        return redirect()
            ->route('accounts.overall', ['account_id' => $bankAccount->id])
            ->with('success', 'Bank account added.');
    }

    /* ───────────────────────────────────────────────────────────────────────
     |  Update
     ────────────────────────────────────────────────────────────────────── */

    public function updateEntry(Request $request, LedgerEntry $entry): RedirectResponse
    {
        if ($entry->isTransfer()) {
            return back()->withErrors([
                'entry' => 'This entry was created by a transfer. Delete the transfer instead.',
            ]);
        }

        $validated = $request->validate([
            'date'        => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'type'        => ['required', 'in:in,out'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $entry->update([
            'date'        => $validated['date'],
            'description' => $validated['description'],
            'amount_in'   => $validated['type'] === 'in'  ? $validated['amount'] : null,
            'amount_out'  => $validated['type'] === 'out' ? $validated['amount'] : null,
            'notes'       => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('accounts.overall', ['account_id' => $entry->bank_account_id])
            ->with('success', 'Entry updated.');
    }

    public function updateBankAccount(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'bank_name'       => ['required', 'string', 'max:255'],
            'account_number'  => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'numeric'],
        ]);

        $bankAccount->update([
            'name'            => $validated['name'],
            'bank_name'       => $validated['bank_name'],
            'account_number'  => $validated['account_number'] ?? null,
            'opening_balance' => $validated['opening_balance'] ?? 0,
        ]);

        return redirect()
            ->route('accounts.overall', ['account_id' => $bankAccount->id])
            ->with('success', 'Account details updated.');
    }

    /* ───────────────────────────────────────────────────────────────────────
     |  Delete
     ────────────────────────────────────────────────────────────────────── */

    public function destroyEntry(LedgerEntry $entry): RedirectResponse
    {
        if ($entry->isTransfer()) {
            return back()->withErrors([
                'entry' => 'This entry is part of a transfer. Delete the transfer instead.',
            ]);
        }

        $accountId = $entry->bank_account_id;
        $entry->delete();

        return redirect()
            ->route('accounts.overall', ['account_id' => $accountId])
            ->with('success', 'Entry removed.');
    }

    public function destroyTransfer(Request $request, Transfer $transfer): RedirectResponse
    {
        $fromAccountId = $transfer->from_account_id;
        TransferService::destroy($transfer);

        $redirectTo = $request->query('redirect_to');

        if ($redirectTo === 'transfers') {
            return redirect()
                ->route('transfers.index')
                ->with('success', 'Transfer reversed. Both legs have been removed.');
        }

        /* Prefer returning to the previous page (ledger / accounts); if there is no
           referrer (e.g. external bookmark), land on the source bank account ledger. */
        return redirect()->back(
            302,
            [],
            route('accounts.overall', ['account_id' => $fromAccountId])
        )->with('success', 'Transfer reversed. Both legs have been removed.');
    }
}
