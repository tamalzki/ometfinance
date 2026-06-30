<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Entity;
use App\Models\Project;
use App\Models\ProjectAllocationLine;
use App\Models\ProjectCollection;
use App\Models\ProjectExpense;
use App\Models\LedgerEntry;
use App\Models\Transfer;
use App\Models\Voucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reports module — five reports + PDF/Excel/Print exports.
 *
 * All monetary aggregates are computed PHP-side because amount/balance
 * columns are encrypted at rest. Look for the `// Encrypted column`
 * comment beside every sum/groupBy in this file.
 */
class ReportController extends Controller
{
    /* ─────────────────────────────────────────────────────────────────
     *  INDEX — Overall Cash Position (default landing)
     * ───────────────────────────────────────────────────────────────── */

    public function index(): View
    {
        $accounts = BankAccount::with('entity')->get();
        $accountIds = $accounts->pluck('id');

        $ledgerByAccount = LedgerEntry::whereIn('bank_account_id', $accountIds)
            ->orderBy('date')
            ->orderBy('id')
            ->get(['bank_account_id', 'amount_in', 'amount_out'])
            ->groupBy('bank_account_id');

        foreach ($accounts as $account) {
            $account->setRelation('ledgerEntries', $ledgerByAccount->get($account->id) ?? collect());
        }

        // Encrypted column — aggregation handled in PHP.
        $totalCashInBank = (float) $accounts->sum(fn ($a) => $a->currentBalance());

        $projectIds = Project::pluck('id');
        $totalProjectExpenses = (float) ProjectExpense::whereIn('project_id', $projectIds)
            ->get(['amount'])
            ->sum(fn ($e) => (float) $e->amount);
        $totalCollections = (float) ProjectCollection::whereIn('project_id', $projectIds)
            ->get(['amount'])
            ->sum(fn ($c) => (float) $c->amount);
        $totalTransferred = (float) Transfer::query()->get(['amount'])->sum(fn ($t) => (float) $t->amount);
        $netCashPosition = $totalCashInBank;

        return view('reports.index', [
            'activeTab'            => 'overall',
            'overall'              => [
                'cash_in_bank'      => $totalCashInBank,
                'project_expenses'  => $totalProjectExpenses,
                'collections'       => $totalCollections,
                'transfers_made'    => $totalTransferred,
                'net_position'      => $netCashPosition,
                'accounts_count'    => $accounts->count(),
                'projects_count'    => $projectIds->count(),
                'transfers_count'   => Transfer::count(),
                'generated_at'      => now(),
            ],
            'filters'              => $this->emptyFilters(),
            'entities'             => $this->allEntities(),
            'projectsForFilter'    => $this->allProjects(),
            'accountsForFilter'    => $this->allAccounts(),
        ]);
    }

    /* ─────────────────────────────────────────────────────────────────
     *  REPORT 1 — Cash Outflow Per Project
     * ───────────────────────────────────────────────────────────────── */

    public function cashOutflow(Request $request): View
    {
        $filters = $this->parseFilters($request, defaultDateRange: true);
        $paginate = ! $request->boolean('_full');

        $query = ProjectExpense::with(['project', 'bankAccount.entity', 'categoryRef.parent'])
            ->whereHas('project');

        if ($filters['date_from']) {
            $query->whereDate('spent_on', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('spent_on', '<=', $filters['date_to']);
        }
        if ($filters['project_id']) {
            $query->where('project_id', $filters['project_id']);
        }
        if ($filters['entity']) {
            $query->whereHas('bankAccount.entity', function ($q) use ($filters) {
                $q->where('slug', $filters['entity'])->orWhere('id', $filters['entity']);
            });
        }
        if ($filters['category_id']) {
            $query->where('category_id', $filters['category_id']);
        }

        $allExpenses = (clone $query)->orderBy('spent_on')->get();
        $grandTotal = (float) $allExpenses->sum(fn ($e) => (float) $e->amount);
        $rowCount = $allExpenses->count();

        $expenses = $paginate
            ? (clone $query)->orderBy('spent_on')->paginate(50)->withQueryString()
            : $allExpenses;

        $pageExpenses = $paginate ? $expenses->getCollection() : $expenses;

        // Encrypted column — group + subtotal in PHP.
        $groups = $pageExpenses->groupBy(fn ($e) => $e->project_id)
            ->map(function ($items) {
                $project = $items->first()->project;

                $categories = $items->groupBy(fn ($e) => $e->category_id ?? 'none')
                    ->map(function ($catItems) {
                        $first = $catItems->first();
                        return (object) [
                            'label'    => $first->categoryRef?->fullLabel() ?? ($first->category ?: 'Uncategorized'),
                            'items'    => $catItems->values(),
                            'subtotal' => (float) $catItems->sum(fn ($e) => (float) $e->amount),
                        ];
                    })
                    ->values();

                return (object) [
                    'project'    => $project,
                    'items'      => $items->values(),
                    'categories' => $categories,
                    'subtotal'   => (float) $items->sum(fn ($e) => (float) $e->amount),
                ];
            })
            ->values();

        return view('reports.index', [
            'activeTab'         => 'cash-outflow',
            'cashOutflow'       => [
                'groups'      => $groups,
                'grand_total' => $grandTotal,
                'row_count'   => $rowCount,
                'paginator'   => $paginate ? $expenses : null,
            ],
            'filters'             => $filters,
            'entities'            => $this->allEntities(),
            'projectsForFilter'   => $this->allProjects(),
            'accountsForFilter'   => $this->allAccounts(),
            'categoriesForFilter' => \App\Models\ProjectCategory::selectOptions(),
        ]);
    }

    /* ─────────────────────────────────────────────────────────────────
     *  REPORT 2 — Account Balances Summary
     * ───────────────────────────────────────────────────────────────── */

    public function accountBalances(Request $request): View
    {
        $filters = $this->parseFilters($request);

        $entitiesQuery = Entity::with(['bankAccounts.ledgerEntries']);
        if ($filters['entity']) {
            $entitiesQuery->where(function ($q) use ($filters) {
                $q->where('slug', $filters['entity'])->orWhere('id', $filters['entity']);
            });
        }
        $entities = $entitiesQuery->get();

        // Encrypted columns — current balance & entity total computed in PHP.
        $groups = $entities->map(function ($entity) {
            $rows = $entity->bankAccounts->map(fn ($a) => (object) [
                'account'    => $a,
                'balance'    => (float) $a->currentBalance(),
            ])->values();
            return (object) [
                'entity'   => $entity,
                'rows'     => $rows,
                'subtotal' => (float) $rows->sum('balance'),
            ];
        });

        $grandTotal = (float) $groups->sum('subtotal');
        $rowCount   = $groups->sum(fn ($g) => $g->rows->count());

        return view('reports.index', [
            'activeTab'         => 'account-balances',
            'accountBalances'   => [
                'groups'      => $groups,
                'grand_total' => $grandTotal,
                'row_count'   => $rowCount,
            ],
            'filters'           => $filters,
            'entities'          => $this->allEntities(),
            'projectsForFilter' => $this->allProjects(),
            'accountsForFilter' => $this->allAccounts(),
        ]);
    }

    /* ─────────────────────────────────────────────────────────────────
     *  REPORT 3 — Transfer History
     * ───────────────────────────────────────────────────────────────── */

    public function transfers(Request $request): View
    {
        $filters = $this->parseFilters($request, defaultDateRange: true);
        $paginate = ! $request->boolean('_full');

        $query = Transfer::with(['fromAccount.entity', 'toAccount.entity', 'fromProject', 'toProject']);

        if ($filters['date_from']) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }
        if ($filters['account_id']) {
            $query->where(function ($q) use ($filters) {
                $q->where('from_account_id', $filters['account_id'])
                  ->orWhere('to_account_id', $filters['account_id']);
            });
        }
        if ($filters['entity']) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('fromAccount.entity', fn ($q2) => $q2->where('slug', $filters['entity'])->orWhere('id', $filters['entity']))
                  ->orWhereHas('toAccount.entity',  fn ($q2) => $q2->where('slug', $filters['entity'])->orWhere('id', $filters['entity']));
            });
        }

        $allTransfers = (clone $query)->orderBy('date')->orderBy('id')->get();
        $grandTotal = (float) $allTransfers->sum(fn ($t) => (float) $t->amount);
        $rowCount = $allTransfers->count();

        $transfers = $paginate
            ? (clone $query)->orderBy('date')->orderBy('id')->paginate(50)->withQueryString()
            : $allTransfers;

        // If an entity was selected we can compute meaningful "out / in"
        // subtotals; otherwise they're identical to the grand total.
        $entityOut = 0.0;
        $entityIn  = 0.0;
        if ($filters['entity']) {
            $entityOut = (float) $allTransfers->filter(fn ($t) => optional($t->fromAccount?->entity)->slug === $filters['entity']
                || (string) optional($t->fromAccount?->entity)->id === (string) $filters['entity']
            )->sum(fn ($t) => (float) $t->amount);
            $entityIn = (float) $allTransfers->filter(fn ($t) => optional($t->toAccount?->entity)->slug === $filters['entity']
                || (string) optional($t->toAccount?->entity)->id === (string) $filters['entity']
            )->sum(fn ($t) => (float) $t->amount);
        }

        return view('reports.index', [
            'activeTab'         => 'transfers',
            'transfers'         => [
                'rows'        => $transfers,
                'grand_total' => $grandTotal,
                'entity_out'  => $entityOut,
                'entity_in'   => $entityIn,
                'row_count'   => $rowCount,
                'paginator'   => $paginate ? $transfers : null,
            ],
            'filters'           => $filters,
            'entities'          => $this->allEntities(),
            'projectsForFilter' => $this->allProjects(),
            'accountsForFilter' => $this->allAccounts(),
        ]);
    }

    /* ─────────────────────────────────────────────────────────────────
     *  REPORT 4 — Collection & Allocation Per Project (external only)
     * ───────────────────────────────────────────────────────────────── */

    public function collections(Request $request): View
    {
        $filters = $this->parseFilters($request, defaultDateRange: true);
        $paginate = ! $request->boolean('_full');

        $query = ProjectCollection::with(['project.allocationLines', 'bankAccount'])
            ->whereHas('project', fn ($q) => $q->where('kind', 'external'));

        if ($filters['date_from']) {
            $query->whereDate('collected_on', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('collected_on', '<=', $filters['date_to']);
        }
        if ($filters['project_id']) {
            $query->where('project_id', $filters['project_id']);
        }

        $allCollections = (clone $query)->orderBy('collected_on')->get();
        $grandTotal = (float) $allCollections->sum(fn ($c) => (float) $c->amount);
        $rowCount = $allCollections->count();

        $collections = $paginate
            ? (clone $query)->orderBy('collected_on')->paginate(50)->withQueryString()
            : $allCollections;

        $pageCollections = $paginate ? $collections->getCollection() : $collections;

        // Encrypted columns — build report rows + subtotal per project in PHP.
        $groups = $pageCollections->groupBy('project_id')->map(function ($items) {
            $project = $items->first()->project;
            $allocations = $project->allocationLines
                ->whereIn('row_kind', [
                    ProjectAllocationLine::KIND_ALLOCATION,
                    ProjectAllocationLine::KIND_KPI,
                ]);

            $rows = collect();
            $subtotal = 0.0;
            foreach ($items as $coll) {
                $collAmt = (float) $coll->amount;
                $subtotal += $collAmt;
                foreach ($allocations as $line) {
                    $rows->push((object) [
                        'collection_date'  => $coll->collected_on,
                        'collection_total' => $collAmt,
                        'category'         => $line->label,
                        'category_kind'    => $line->row_kind,
                        'percent'          => (float) $line->percent,
                        'amount'           => $collAmt * (float) $line->percent,
                    ]);
                }
            }
            return (object) [
                'project'  => $project,
                'rows'     => $rows->values(),
                'subtotal' => $subtotal,
            ];
        })->values();

        return view('reports.index', [
            'activeTab'         => 'collections',
            'collections'       => [
                'groups'      => $groups,
                'grand_total' => $grandTotal,
                'row_count'   => $rowCount,
                'paginator'   => $paginate ? $collections : null,
            ],
            'filters'           => $filters,
            'entities'          => $this->allEntities(),
            'projectsForFilter' => $this->allProjects()->where('kind', 'external')->values(),
            'accountsForFilter' => $this->allAccounts(),
        ]);
    }

    /* ─────────────────────────────────────────────────────────────────
     *  REPORT 5 — Payables Aging (open vouchers)
     * ───────────────────────────────────────────────────────────────── */

    public function payables(Request $request): View
    {
        $filters = $this->parseFilters($request, defaultDateRange: true);
        $paginate = ! $request->boolean('_full');

        $query = Voucher::with(['project', 'sourceBankAccount.entity', 'payments'])
            ->whereIn('status', ['unpaid', 'partial', 'pdc'])
            ->where('approval_status', 'approved');

        if ($filters['date_from']) {
            $query->whereDate('due_date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('due_date', '<=', $filters['date_to']);
        }
        if ($filters['project_id']) {
            $query->where('project_id', $filters['project_id']);
        }

        $allOpen = (clone $query)->orderBy('due_date')->orderByDesc('voucher_date')->get();
        $grandTotal = (float) $allOpen->sum(fn ($v) => $v->balanceDue());
        $rowCount = $allOpen->count();

        $open = $paginate
            ? (clone $query)->orderBy('due_date')->orderByDesc('voucher_date')->paginate(50)->withQueryString()
            : $allOpen;

        $pageOpen = $paginate ? $open->getCollection() : $open;

        // Encrypted amounts — group by aging bucket in PHP.
        $labels = VoucherController::AGING_LABELS;
        $groups = collect($labels)->map(function ($label, $key) use ($pageOpen) {
            $items = $pageOpen->filter(fn ($v) => $v->agingBucket() === $key)->values();
            return (object) [
                'key'      => $key,
                'label'    => $label,
                'items'    => $items,
                'subtotal' => (float) $items->sum(fn ($v) => $v->balanceDue()),
            ];
        })->filter(fn ($g) => $g->items->isNotEmpty())->values();

        return view('reports.index', [
            'activeTab'         => 'payables',
            'payables'          => [
                'groups'      => $groups,
                'grand_total' => $grandTotal,
                'row_count'   => $rowCount,
                'paginator'   => $paginate ? $open : null,
            ],
            'filters'           => $filters,
            'entities'          => $this->allEntities(),
            'projectsForFilter' => $this->allProjects(),
            'accountsForFilter' => $this->allAccounts(),
        ]);
    }

    /* ─────────────────────────────────────────────────────────────────
     *  REPORT 6 — Voucher Register (Accounting Staff export tool)
     *
     *  Lets Accounting filter and export their vouchers by source
     *  (Main / BGC), status (paid / unpaid / etc.), project, and type.
     *  Accounting Staff only ever see what they themselves submitted,
     *  mirroring the same scoping VoucherController::index() applies.
     * ───────────────────────────────────────────────────────────────── */

    public function vouchers(Request $request): View
    {
        $filters = $this->parseFilters($request, defaultDateRange: true);
        $paginate = ! $request->boolean('_full');

        $query = Voucher::with(['project', 'sourceBankAccount.entity', 'payments']);

        if (auth()->user()->isAccounting()) {
            $query->whereHas('approvalRequests', fn ($q) => $q
                ->where('type', \App\Models\VoucherRequest::TYPE_CREATE)
                ->where('requested_by', auth()->id()));
        }

        if ($filters['date_from']) {
            $query->whereDate('voucher_date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('voucher_date', '<=', $filters['date_to']);
        }
        if ($filters['project_id']) {
            $query->where('project_id', $filters['project_id']);
        }
        if ($filters['source']) {
            $query->where('source', $filters['source']);
        }
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }
        if ($filters['transaction_type']) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        $allVouchers = (clone $query)->orderBy('voucher_date')->orderBy('id')->get();
        $grandPayable = (float) $allVouchers->sum(fn ($v) => (float) $v->amount_payable);
        $grandPaid    = (float) $allVouchers->sum(fn ($v) => $v->amountPaid());
        $grandBalance = (float) $allVouchers->sum(fn ($v) => $v->balanceDue());
        $rowCount     = $allVouchers->count();

        $vouchers = $paginate
            ? (clone $query)->orderBy('voucher_date')->orderBy('id')->paginate(50)->withQueryString()
            : $allVouchers;

        return view('reports.index', [
            'activeTab'         => 'vouchers',
            'vouchersReport'     => [
                'rows'          => $vouchers,
                'grand_payable' => $grandPayable,
                'grand_paid'    => $grandPaid,
                'grand_balance' => $grandBalance,
                'row_count'     => $rowCount,
                'paginator'     => $paginate ? $vouchers : null,
            ],
            'filters'            => $filters,
            'entities'           => $this->allEntities(),
            'projectsForFilter'  => $this->allProjects(),
            'accountsForFilter'  => $this->allAccounts(),
            'sourcesForFilter'   => Voucher::SOURCES,
            'statusesForFilter'  => Voucher::STATUSES,
            'typesForFilter'     => Voucher::TYPES,
        ]);
    }

    /* ─────────────────────────────────────────────────────────────────
     *  EXPORT — PDF
     * ───────────────────────────────────────────────────────────────── */

    public function exportPdf(Request $request)
    {
        $report  = $request->input('report', 'overall');
        $payload = $this->buildExportPayload($report, $request);

        $pdf = Pdf::loadView('reports.pdf.report', $payload)
            ->setPaper('A4', $payload['orientation'] ?? 'portrait');

        $filename = sprintf('omet-%s-%s.pdf', $payload['filename_key'], now()->format('Ymd_His'));

        return $pdf->download($filename);
    }

    /* ─────────────────────────────────────────────────────────────────
     *  EXPORT — Excel (.xlsx via Laravel Excel)
     * ───────────────────────────────────────────────────────────────── */

    public function exportExcel(Request $request)
    {
        $report  = $request->input('report', 'overall');
        $payload = $this->buildExportPayload($report, $request);

        $sheet = new class($payload) implements FromArray, WithHeadings, WithTitle {
            public function __construct(private array $p) {}
            public function array(): array { return $this->p['rows']; }
            public function headings(): array { return $this->p['headings']; }
            public function title(): string { return substr($this->p['title'], 0, 31); }
        };

        $filename = sprintf('omet-%s-%s.xlsx', $payload['filename_key'], now()->format('Ymd_His'));

        return Excel::download($sheet, $filename);
    }

    /* ═════════════════════════════════════════════════════════════════
     *  Helpers — shared between report methods + exports
     * ═════════════════════════════════════════════════════════════════ */

    private function parseFilters(Request $request, bool $defaultDateRange = false): array
    {
        $filters = [
            'date_from'        => $request->filled('date_from') ? Carbon::parse($request->input('date_from'))->toDateString() : null,
            'date_to'          => $request->filled('date_to')   ? Carbon::parse($request->input('date_to'))->toDateString()   : null,
            'project_id'       => $request->input('project_id') ?: null,
            'entity'           => $request->input('entity') ?: null,
            'account_id'       => $request->input('account_id') ?: null,
            'category_id'      => $request->input('category_id') ?: null,
            'source'           => $request->input('source') ?: null,
            'status'           => $request->input('status') ?: null,
            'transaction_type' => $request->input('transaction_type') ?: null,
        ];

        if ($defaultDateRange && ! $filters['date_from'] && ! $filters['date_to']) {
            $filters['date_from'] = now()->subDays(90)->toDateString();
            $filters['date_to']   = now()->toDateString();
        }

        return $filters;
    }

    private function emptyFilters(): array
    {
        return [
            'date_from'        => null,
            'date_to'          => null,
            'project_id'       => null,
            'entity'           => null,
            'account_id'       => null,
            'category_id'      => null,
            'source'           => null,
            'status'           => null,
            'transaction_type' => null,
        ];
    }

    private function allEntities(): Collection
    {
        return Entity::orderBy('name')->get(['id', 'name', 'slug']);
    }

    private function allProjects(): Collection
    {
        return Project::orderBy('name')->get(['id', 'name', 'kind']);
    }

    private function allAccounts(): Collection
    {
        return BankAccount::with('entity:id,name')->orderBy('name')->get();
    }

    /**
     * Build a flat export payload (title, headings, rows[]) for whichever
     * report was requested. The PDF + Excel exporters both consume this.
     *
     * Encrypted columns — all monetary values come from Eloquent models,
     * which decrypt on attribute access, so the rows array contains
     * plaintext strings ready for either renderer.
     */
    private function buildExportPayload(string $report, Request $request): array
    {
        $filters = $this->parseFilters($request);
        $exportFilters = array_merge($filters, ['_full' => true]);
        $range   = $this->formatRange($filters);

        switch ($report) {
            case 'cash-outflow':
                $req = new Request($exportFilters);
                $data = $this->cashOutflow($req)->getData();
                $rows = [];
                foreach ($data['cashOutflow']['groups'] as $g) {
                    foreach ($g->items as $e) {
                        $rows[] = [
                            $g->project->name,
                            optional($e->spent_on)->format('Y-m-d'),
                            $e->description ?? '',
                            $e->categoryRef?->fullLabel() ?? $e->category ?? '',
                            number_format((float) $e->amount, 2, '.', ''),
                        ];
                    }
                    $rows[] = ['', '', '', 'Subtotal — ' . $g->project->name, number_format($g->subtotal, 2, '.', '')];
                }
                $rows[] = ['', '', '', 'GRAND TOTAL', number_format($data['cashOutflow']['grand_total'], 2, '.', '')];
                return [
                    'title'        => 'Cash Outflow Per Project',
                    'range'        => $range,
                    'headings'     => ['Project', 'Date', 'Description', 'Category', 'Amount'],
                    'rows'         => $rows,
                    'filename_key' => 'cash-outflow',
                    'orientation'  => 'portrait',
                    'view_data'    => $data,
                ];

            case 'account-balances':
                $req = new Request($exportFilters);
                $data = $this->accountBalances($req)->getData();
                $rows = [];
                foreach ($data['accountBalances']['groups'] as $g) {
                    foreach ($g->rows as $r) {
                        $rows[] = [
                            $g->entity->name,
                            $r->account->bank_name,
                            $r->account->name,
                            number_format($r->balance, 2, '.', ''),
                        ];
                    }
                    $rows[] = ['', '', 'Subtotal — ' . $g->entity->name, number_format($g->subtotal, 2, '.', '')];
                }
                $rows[] = ['', '', 'GRAND TOTAL CASH IN BANK', number_format($data['accountBalances']['grand_total'], 2, '.', '')];
                return [
                    'title'        => 'Account Balances Summary',
                    'range'        => $range,
                    'headings'     => ['Entity', 'Bank', 'Account Name', 'Current Balance'],
                    'rows'         => $rows,
                    'filename_key' => 'account-balances',
                    'orientation'  => 'portrait',
                    'view_data'    => $data,
                ];

            case 'transfers':
                $req = new Request($exportFilters);
                $data = $this->transfers($req)->getData();
                $rows = [];
                foreach ($data['transfers']['rows'] as $t) {
                    $rows[] = [
                        optional($t->date)->format('Y-m-d'),
                        optional($t->fromAccount)->name . ($t->fromAccount?->entity ? ' (' . $t->fromAccount->entity->name . ')' : ''),
                        optional($t->toAccount)->name   . ($t->toAccount?->entity ? ' (' . $t->toAccount->entity->name . ')' : ''),
                        number_format((float) $t->amount, 2, '.', ''),
                        $t->memo ?? '',
                        optional($t->created_at)->format('Y-m-d H:i'),
                    ];
                }
                $rows[] = ['', '', 'GRAND TOTAL', number_format($data['transfers']['grand_total'], 2, '.', ''), '', ''];
                return [
                    'title'        => 'Transfer History',
                    'range'        => $range,
                    'headings'     => ['Date', 'From Account', 'To Account', 'Amount', 'Memo', 'Recorded At'],
                    'rows'         => $rows,
                    'filename_key' => 'transfers',
                    'orientation'  => 'landscape',
                    'view_data'    => $data,
                ];

            case 'collections':
                $req = new Request($exportFilters);
                $data = $this->collections($req)->getData();
                $rows = [];
                foreach ($data['collections']['groups'] as $g) {
                    foreach ($g->rows as $r) {
                        $rows[] = [
                            $g->project->name,
                            $r->collection_date->format('Y-m-d'),
                            number_format($r->collection_total, 2, '.', ''),
                            $r->category,
                            number_format($r->percent * 100, 2) . '%',
                            number_format($r->amount, 2, '.', ''),
                        ];
                    }
                    $rows[] = ['', '', '', '', 'Subtotal — ' . $g->project->name, number_format($g->subtotal, 2, '.', '')];
                }
                $rows[] = ['', '', '', '', 'GRAND TOTAL COLLECTED', number_format($data['collections']['grand_total'], 2, '.', '')];
                return [
                    'title'        => 'Collection & Allocation Per Project',
                    'range'        => $range,
                    'headings'     => ['Project', 'Collection Date', 'Total Collected', 'Allocation Category', '%', 'Amount'],
                    'rows'         => $rows,
                    'filename_key' => 'collections',
                    'orientation'  => 'landscape',
                    'view_data'    => $data,
                ];

            case 'payables':
                $req = new Request($exportFilters);
                $data = $this->payables($req)->getData();
                $rows = [];
                foreach ($data['payables']['groups'] as $g) {
                    foreach ($g->items as $v) {
                        $rows[] = [
                            $v->voucher_no,
                            $v->payee_name,
                            $v->project?->name ?? '',
                            optional($v->due_date)->format('Y-m-d') ?? '',
                            $g->label,
                            number_format($v->balanceDue(), 2, '.', ''),
                        ];
                    }
                    $rows[] = ['', '', '', '', 'Subtotal — ' . $g->label, number_format($g->subtotal, 2, '.', '')];
                }
                $rows[] = ['', '', '', '', 'TOTAL OUTSTANDING', number_format($data['payables']['grand_total'], 2, '.', '')];
                return [
                    'title'        => 'Payables Aging',
                    'range'        => $range,
                    'headings'     => ['Voucher', 'Payee', 'Project', 'Due Date', 'Aging', 'Balance'],
                    'rows'         => $rows,
                    'filename_key' => 'payables',
                    'orientation'  => 'portrait',
                    'view_data'    => $data,
                ];

            case 'vouchers':
                $req = new Request($exportFilters);
                $data = $this->vouchers($req)->getData();
                $rows = [];
                foreach ($data['vouchersReport']['rows'] as $v) {
                    $rows[] = [
                        $v->voucher_no,
                        optional($v->voucher_date)->format('Y-m-d'),
                        $v->payee_name,
                        $v->project?->name ?? '',
                        $v->sourceLabel(),
                        $v->typeLabel(),
                        $v->statusLabel(),
                        number_format((float) $v->amount_payable, 2, '.', ''),
                        number_format($v->amountPaid(), 2, '.', ''),
                        number_format($v->balanceDue(), 2, '.', ''),
                    ];
                }
                $rows[] = [
                    '', '', '', '', '', '', 'GRAND TOTAL',
                    number_format($data['vouchersReport']['grand_payable'], 2, '.', ''),
                    number_format($data['vouchersReport']['grand_paid'], 2, '.', ''),
                    number_format($data['vouchersReport']['grand_balance'], 2, '.', ''),
                ];
                return [
                    'title'        => auth()->user()->isAccounting() ? 'Voucher Reports' : 'Voucher Register',
                    'range'        => $range,
                    'headings'     => ['Voucher', 'Date', 'Payee', 'Project', 'Source', 'Type', 'Status', 'Payable', 'Paid', 'Balance'],
                    'rows'         => $rows,
                    'filename_key' => auth()->user()->isAccounting() ? 'voucher-reports' : 'voucher-register',
                    'orientation'  => 'landscape',
                    'view_data'    => $data,
                ];

            case 'overall':
            default:
                $data = $this->index()->getData();
                $o = (object) $data['overall'];
                return [
                    'title'        => 'Overall Cash Position',
                    'range'        => 'As of ' . now()->format('M j, Y g:i A'),
                    'headings'     => ['Metric', 'Value'],
                    'rows'         => [
                        ['Total cash in bank',         number_format($o->cash_in_bank, 2, '.', '')],
                        ['Total project expenses',     number_format($o->project_expenses, 2, '.', '')],
                        ['Total collections received', number_format($o->collections, 2, '.', '')],
                        ['Total transfers made',       number_format($o->transfers_made, 2, '.', '')],
                        ['Net cash position',          number_format($o->net_position, 2, '.', '')],
                    ],
                    'filename_key' => 'overall',
                    'orientation'  => 'portrait',
                    'view_data'    => $data,
                ];
        }
    }

    private function formatRange(array $filters): string
    {
        if (! $filters['date_from'] && ! $filters['date_to']) {
            return 'All dates';
        }
        $from = $filters['date_from'] ? Carbon::parse($filters['date_from'])->format('M j, Y') : 'beginning';
        $to   = $filters['date_to']   ? Carbon::parse($filters['date_to'])->format('M j, Y')   : 'today';
        return "From {$from} to {$to}";
    }
}
