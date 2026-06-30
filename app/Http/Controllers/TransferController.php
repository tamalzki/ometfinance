<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Http\Requests\UpdateTransferRequest;
use App\Models\BankAccount;
use App\Models\Project;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransferController extends Controller
{
    /**
     * Central intercompany register.
     */
    public function index(Request $request): View
    {
        $from = $request->query('from');
        $to   = $request->query('to');

        if (! $request->has('from') && ! $request->has('to')) {
            $from = now()->subDays(90)->toDateString();
            $to   = now()->toDateString();
        }

        $baseQuery = Transfer::with([
            'fromAccount.entity', 'toAccount.entity',
            'fromProject', 'toProject',
        ])->orderByDesc('date')->orderByDesc('id');

        if ($from) {
            $baseQuery->whereDate('date', '>=', $from);
        }
        if ($to) {
            $baseQuery->whereDate('date', '<=', $to);
        }

        $summaryRows = (clone $baseQuery)->with(['fromAccount:id,entity_id', 'toAccount:id,entity_id'])
            ->get(['id', 'amount', 'from_account_id', 'to_account_id', 'from_project_id', 'to_project_id']);

        $summary = [
            'count'          => $summaryRows->count(),
            // Encrypted column — aggregation handled in PHP via Collection sum.
            'total'          => (float) $summaryRows->sum(fn ($t) => (float) $t->amount),
            'intercompany'   => $summaryRows->filter(fn ($t) => $t->isIntercompany())->count(),
            'project_linked' => $summaryRows->filter(fn ($t) => $t->hasProjectImpact())->count(),
        ];

        $transfers = (clone $baseQuery)->paginate(50)->withQueryString();

        $rowSearchIndex = [];
        foreach ($transfers->getCollection() as $t) {
            $rowSearchIndex[$t->id] = strtolower(implode(' ', array_filter([
                $t->fromAccount?->name,
                $t->fromAccount?->entity?->name,
                $t->toAccount?->name,
                $t->toAccount?->entity?->name,
                $t->fromProject?->name,
                $t->toProject?->name,
                $t->purposeLabel(),
                $t->memo,
                $t->reason,
            ])));
        }

        $allAccounts = BankAccount::with('entity')->orderBy('name')->get();
        $projects    = Project::orderBy('kind')->orderBy('name')->get(['id', 'name', 'kind', 'code', 'client_name']);

        return view('transfers.index', compact(
            'transfers', 'summary', 'allAccounts', 'projects',
            'from', 'to', 'rowSearchIndex'
        ));
    }

    public function store(StoreTransferRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $transfer = TransferService::create($validated);

        return redirect()->route('transfers.index')
            ->with('success', sprintf(
                'Transfer recorded: ₱%s from %s → %s. %s',
                number_format($transfer->amount, 2),
                $transfer->fromAccount?->name,
                $transfer->toAccount?->name,
                $transfer->hasProjectImpact() ? 'Project books were also updated.' : ''
            ));
    }

    public function update(UpdateTransferRequest $request, Transfer $transfer): RedirectResponse
    {
        $validated = $request->validated();

        $transfer = TransferService::update($transfer, $validated);

        return redirect()->route('transfers.index')
            ->with('success', sprintf(
                'Transfer updated: ₱%s from %s → %s.',
                number_format($transfer->amount, 2),
                $transfer->fromAccount?->name,
                $transfer->toAccount?->name
            ));
    }

    public function destroy(Transfer $transfer): RedirectResponse
    {
        TransferService::destroy($transfer);

        return redirect()->route('transfers.index')
            ->with('success', 'Transfer deleted. Linked bank ledger and project entries have been removed.');
    }
}
