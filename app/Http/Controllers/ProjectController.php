<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Project;
use App\Models\ProjectAllocationLine;
use App\Models\ProjectCollection;
use App\Models\ProjectExpense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /* ── Listings ─────────────────────────────────────────────────────────── */

    public function index(): RedirectResponse
    {
        return redirect()->route('projects.external');
    }

    public function external(): View
    {
        return $this->kindIndex('external');
    }

    public function inHouse(): View
    {
        return $this->kindIndex('in_house');
    }

    private function kindIndex(string $kind): View
    {
        $projectIds = Project::where('kind', $kind)->pluck('id');

        $collectionsByProject = ProjectCollection::whereIn('project_id', $projectIds)
            ->get(['project_id', 'amount', 'collected_on'])
            ->groupBy('project_id');

        $expensesByProject = ProjectExpense::whereIn('project_id', $projectIds)
            ->get(['project_id', 'amount', 'spent_on'])
            ->groupBy('project_id');

        $allProjects = Project::where('kind', $kind)->get(['id', 'contract_value', 'status']);

        $projects = Project::where('kind', $kind)
            ->latest()
            ->paginate(50)->withQueryString();

        foreach ($projects as $project) {
            $project->setRelation('collections', $collectionsByProject->get($project->id) ?? collect());
            $project->setRelation('expenses', $expensesByProject->get($project->id) ?? collect());
        }

        $activeProjects = $allProjects->whereNotIn('status', ['completed', 'cancelled']);

        $totalCollected = (float) $collectionsByProject->flatten()->sum(fn ($c) => (float) $c->amount);
        $totalOutflow = (float) $expensesByProject->flatten()->sum(fn ($e) => (float) $e->amount);

        $summary = [
            'active_count'     => $activeProjects->count(),
            'total_count'      => $allProjects->count(),
            'completed_count'  => $allProjects->where('status', 'completed')->count(),
            'contract_value'   => (float) $activeProjects->sum('contract_value'),
            'total_collected'  => $totalCollected,
            'total_outflow'    => $totalOutflow,
        ];
        $summary['outstanding'] = max(0, $summary['contract_value'] - $summary['total_collected']);
        $summary['net_cash']    = $summary['total_collected'] - $summary['total_outflow'];
        $summary['collection_pct'] = $summary['contract_value'] > 0
            ? round($summary['total_collected'] / $summary['contract_value'] * 100, 1)
            : 0;

        // In-house-specific health insights for the landing page.
        if ($kind === 'in_house') {
            $threshold30d = now()->subDays(30);

            $summary['over_budget'] = $allProjects->filter(function ($p) use ($expensesByProject) {
                $budget = (float) $p->contract_value;
                $spent  = (float) ($expensesByProject->get($p->id)?->sum(fn ($e) => (float) $e->amount) ?? 0);
                return $budget > 0 && $spent > $budget;
            })->count();

            $summary['nearing_limit'] = $allProjects->filter(function ($p) use ($expensesByProject) {
                $budget = (float) $p->contract_value;
                $spent  = (float) ($expensesByProject->get($p->id)?->sum(fn ($e) => (float) $e->amount) ?? 0);
                if ($budget <= 0) {
                    return false;
                }
                $pct = $spent / $budget;
                return $pct >= 0.8 && $pct < 1.0;
            })->count();

            $summary['active_recently'] = $allProjects->filter(function ($p) use ($threshold30d, $expensesByProject, $collectionsByProject) {
                $expenses = $expensesByProject->get($p->id);
                $collections = $collectionsByProject->get($p->id);
                $lastExpense = $expenses?->max('spent_on');
                $lastInflow  = $collections?->max('collected_on');
                $last = collect([$lastExpense, $lastInflow])->filter()->max();
                return $last && $last >= $threshold30d;
            })->count();
        }

        $kindLabel = $kind === 'in_house' ? 'In-house Projects' : 'External Projects';

        return view('projects.index', compact('projects', 'summary', 'kind', 'kindLabel'));
    }

    /* ── Create project ───────────────────────────────────────────────────── */

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'kind'           => ['required', 'in:in_house,external'],
            'code'           => ['nullable', 'string', 'max:50'],
            'client_name'    => ['nullable', 'required_if:kind,external', 'string', 'max:255'],
            'location'       => ['nullable', 'string', 'max:255'],
            'status'         => ['required', 'in:planning,active,in_progress,on-hold,completed,cancelled'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date', 'after_or_equal:start_date'],
            'due_date'       => ['nullable', 'date'],
            'image'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if (($validated['kind'] ?? '') === 'in_house' && empty($validated['client_name'])) {
            $validated['client_name'] = 'Onemark (internal)';
        }

        $validated['contract_value'] = $validated['contract_value'] ?? 0;

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('projects', 'public');
        }
        unset($validated['image']);

        $project = Project::create($validated);

        // For external projects: save allocation lines if provided
        $labels   = $request->input('alloc_label', []);
        $percents = $request->input('alloc_percent', []);
        foreach ($labels as $i => $label) {
            $label   = trim($label);
            $percent = (float) ($percents[$i] ?? 0);
            if ($label && $percent > 0) {
                ProjectAllocationLine::create([
                    'project_id' => $project->id,
                    'label'      => $label,
                    'percent'    => $percent / 100,
                    'sort_order' => $i,
                ]);
            }
        }

        $project->ensureDefaultExternalAllocationLines();

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    /* ── Delete a project ─────────────────────────────────────────────────── */

    public function destroy(Project $project): RedirectResponse
    {
        $redirectRoute = $project->isInHouse() ? 'projects.in_house' : 'projects.external';

        if ($project->image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($project->image_path);
        }

        $project->delete();

        return redirect()->route($redirectRoute)->with('success', 'Project deleted.');
    }

    public function updateImage(Request $request, Project $project): RedirectResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if ($project->image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($project->image_path);
        }

        $project->update([
            'image_path' => $request->file('image')->store('projects', 'public'),
        ]);

        return back()->with('success', 'Project image updated.');
    }

    /* ── Single project detail ────────────────────────────────────────────── */

    public function show(Project $project): RedirectResponse
    {
        return redirect()->route('projects.show.overview', $project);
    }

    public function showOverview(Project $project): View
    {
        $folder = $project->isExternal() ? 'external' : 'in_house';
        return view("projects.{$folder}.overview", $this->loadProjectData($project));
    }

    public function showAllocation(Project $project)
    {
        if (! $project->isExternal()) {
            return redirect()->route('projects.show.overview', $project);
        }

        $data = $this->loadProjectData($project);

        // Simple chronological log of percent changes — one row per line per
        // edit, reusing the Auditable trail already written on update().
        $data['allocationHistory'] = \App\Models\AuditLog::where('auditable_type', ProjectAllocationLine::class)
            ->whereIn('auditable_id', $project->allocationLines->pluck('id'))
            ->where('event', 'updated')
            ->whereNotNull('new_values->percent')
            ->with('user')
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get();

        return view('projects.external.allocation', $data);
    }

    public function showSummary(Project $project)
    {
        if ($project->isExternal()) {
            return redirect()->route('projects.show.overview', $project);
        }

        $data = $this->loadProjectData($project);

        $categorySummary = \App\Models\ProjectCategory::whereNull('parent_id')->orderBy('name')->get()
            ->map(function ($parent) use ($project) {
                $amount = $project->expenses
                    ->filter(fn ($e) => $e->categoryRef && ($e->categoryRef->id === $parent->id || $e->categoryRef->parent_id === $parent->id))
                    ->sum(fn ($e) => (float) $e->amount);

                return ['id' => $parent->id, 'label' => $parent->name, 'amount' => (float) $amount];
            });

        $uncategorized = (float) $project->expenses
            ->filter(fn ($e) => ! $e->categoryRef)
            ->sum(fn ($e) => (float) $e->amount);

        if ($uncategorized > 0) {
            $categorySummary->push(['id' => null, 'label' => 'Uncategorized', 'amount' => $uncategorized]);
        }

        $data['categorySummary'] = $categorySummary->values();
        $data['totalCost'] = $project->totalExpenses();

        return view('projects.in_house.summary', $data);
    }

    public function showInflow(Project $project): View
    {
        $template = $project->isExternal() ? 'projects.external.inflow' : 'projects.in_house.funding';
        return view($template, $this->loadProjectData($project));
    }

    public function showOutflow(Project $project): View
    {
        $template = $project->isExternal() ? 'projects.external.outflow' : 'projects.in_house.outflow';
        return view($template, $this->loadProjectData($project));
    }

    public function showHistory(Project $project): View
    {
        $template = $project->isExternal() ? 'projects.external.history' : 'projects.in_house.ledger';
        return view($template, $this->loadProjectData($project));
    }

    private function loadProjectData(Project $project): array
    {
        $project->load([
            'collections.bankAccount',
            'collections.transfer.fromAccount.entity',
            'collections.transfer.fromProject',
            'expenses.bankAccount',
            'expenses.transfer.toAccount.entity',
            'expenses.transfer.toProject',
            'expenses.voucher',
            'expenses.categoryRef.parent',
            'allocationLines',
        ]);

        $bankAccounts = BankAccount::with('entity')->orderBy('name')->get();

        $collectionsChrono = $project->collections->sortBy('collected_on')->values();

        // Source projects offered in the "support from another project" select.
        $otherProjects = Project::where('id', '!=', $project->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('kind')
            ->orderBy('name')
            ->get(['id', 'name', 'kind']);

        return compact('project', 'bankAccounts', 'collectionsChrono', 'otherProjects');
    }

    /* ── Update project ──────────────────────────────────────────────────── */

    public function update(Request $request, Project $project): RedirectResponse
    {
        // Edit form does not POST `kind`; require client when the record is external.
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'code'           => ['nullable', 'string', 'max:50'],
            'client_name'    => ['nullable', Rule::requiredIf($project->isExternal()), 'string', 'max:255'],
            'location'       => ['nullable', 'string', 'max:255'],
            'status'         => ['required', 'in:planning,active,in_progress,on-hold,completed,cancelled'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date', 'after_or_equal:start_date'],
            'due_date'       => ['nullable', 'date'],
        ]);

        $validated['contract_value'] = $validated['contract_value'] ?? 0;

        $project->update($validated);

        $project->ensureDefaultExternalAllocationLines();

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project updated.');
    }

    /* ── Adjust allocation (budget distribution can shift with project status) ── */

    public function updateAllocation(\App\Http\Requests\UpdateProjectAllocationRequest $request, Project $project): RedirectResponse
    {
        $lines = $project->allocationLines()->get()->keyBy('id');

        foreach ($request->validated()['percents'] as $lineId => $percent) {
            $lines->get((int) $lineId)?->update(['percent' => $percent / 100]);
        }

        return redirect()->route('projects.show.allocation', $project)
            ->with('success', 'Allocation adjusted.');
    }

    /* ── Record a collection (inflow) ────────────────────────────────────── */

    public function storeCollection(\App\Http\Requests\StoreProjectCollectionRequest $request, Project $project): RedirectResponse
    {
        // In-house projects have no client income: every inflow is borrowed
        // funds or support from another project, which must go through the
        // funding flow so bank ledgers stay in sync.
        if ($project->isInHouse()) {
            return back()->withErrors([
                'collection' => 'In-house projects are funded from other accounts. Use the Funding form instead.',
            ]);
        }

        $project->collections()->create($this->withComputedDeductions($request->validated()));

        return back()->with('success', 'Collection recorded.');
    }

    /**
     * Deduction rates come from the form; the deducted amounts are always
     * derived here from `amount * rate` so a tampered or stale client-side
     * computation can never end up stored as the "real" deduction.
     */
    private function withComputedDeductions(array $data): array
    {
        $amount = (float) $data['amount'];

        foreach (['vat', 'wht', 'retention', 'recoupment'] as $deduction) {
            $rate = (float) ($data["{$deduction}_rate"] ?? 0);
            $data["{$deduction}_amount"] = round($amount * $rate / 100, 2);
        }

        $data['other_deductions_amount'] = round((float) ($data['other_deductions_amount'] ?? 0), 2);

        return $data;
    }

    /* ── Record funding (borrow / support from another account) ─────────── */

    public function storeFunding(\App\Http\Requests\StoreProjectFundingRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();

        $transfer = \App\Services\TransferService::create([
            'from_account_id' => $validated['from_account_id'],
            'to_account_id'   => $validated['to_account_id'],
            'from_project_id' => $validated['from_project_id'] ?? null,
            'to_project_id'   => $project->id,
            'date'            => $validated['date'],
            'amount'          => $validated['amount'],
            'purpose'         => 'project_funding',
            'memo'            => 'Funding for ' . $project->name,
            'reason'          => $validated['notes'] ?? null,
        ]);

        $sourceLabel = $transfer->fromProject
            ? $transfer->fromProject->name
            : $transfer->fromAccount?->name;

        return back()->with('success', sprintf(
            '₱%s funded from %s. Both bank ledgers were updated.',
            number_format((float) $transfer->amount, 2),
            $sourceLabel
        ));
    }

    /* ── Delete a collection ─────────────────────────────────────────────── */

    public function destroyCollection(ProjectCollection $collection): RedirectResponse
    {
        if ($collection->isFromTransfer()) {
            return back()->withErrors([
                'collection' => 'This inflow was created from a transfer. Reverse the transfer from the Transfers page to remove it.',
            ]);
        }

        $collection->delete();

        return back()->with('success', 'Inflow entry removed.');
    }

    /* ── Delete an expense ───────────────────────────────────────────────── */

    public function destroyExpense(ProjectExpense $expense): RedirectResponse
    {
        if ($expense->isFromTransfer()) {
            return back()->withErrors([
                'expense' => 'This outflow was created from a transfer. Reverse the transfer from the Transfers page to remove it.',
            ]);
        }

        if ($expense->isFromVoucher()) {
            return back()->withErrors([
                'expense' => 'This outflow was posted by a voucher payment. Reverse the payment from Daily Transactions to remove it.',
            ]);
        }

        $expense->delete();

        return back()->with('success', 'Outflow entry removed.');
    }

    /* ── Export project tables as CSV (Excel-compatible) ─────────────────── */

    public function export(Project $project, string $section): StreamedResponse
    {
        $section = strtolower($section);
        if (!in_array($section, ['allocation', 'inflow', 'outflow'], true)) {
            abort(404);
        }

        $project->load(['collections.bankAccount', 'expenses.bankAccount', 'allocationLines']);
        $safeName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $project->name) ?: 'project';
        $filename = sprintf('%s-%s-%s.csv', $safeName, $section, now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($project, $section): void {
            $out = fopen('php://output', 'w');
            if (!$out) {
                return;
            }

            fputcsv($out, ['Project', $project->name]);
            fputcsv($out, ['Type', $project->kind]);
            fputcsv($out, ['Status', $project->status]);
            fputcsv($out, []);

            if ($section === 'allocation') {
                $collectionsChrono = $project->collections->sortBy('collected_on')->values();
                $totalCollected = (float) $project->totalCollected();
                $allocLines = $project->allocationLines;

                $header = ['Category', '%', 'Total Collected'];
                foreach ($collectionsChrono as $idx => $coll) {
                    $header[] = sprintf('%d%s Collection (%s)', $idx + 1, $this->ordinalSuffix($idx + 1), $coll->collected_on->format('Y-m-d'));
                }
                fputcsv($out, $header);

                foreach ($allocLines as $line) {
                    if ($line->row_kind === ProjectAllocationLine::KIND_BLANK) {
                        fputcsv($out, []);
                        continue;
                    }
                    $percent = (float) $line->percent;
                    $row = [
                        $line->label ?: '',
                        number_format($percent * 100, 2) . '%',
                        number_format($totalCollected * $percent, 2, '.', ''),
                    ];
                    foreach ($collectionsChrono as $coll) {
                        $row[] = number_format((float) $coll->amount * $percent, 2, '.', '');
                    }
                    fputcsv($out, $row);
                }
            }

            if ($section === 'inflow') {
                fputcsv($out, ['Date', 'Reference', 'Deposited To', 'Amount', 'Notes']);
                foreach ($project->collections->sortBy('collected_on')->values() as $c) {
                    fputcsv($out, [
                        optional($c->collected_on)->format('Y-m-d'),
                        $c->reference,
                        optional($c->bankAccount)->name,
                        number_format((float) $c->amount, 2, '.', ''),
                        $c->notes,
                    ]);
                }
            }

            if ($section === 'outflow') {
                fputcsv($out, ['Date', 'Description', 'Category', 'Vendor / Ref', 'Paid From', 'Amount', 'Notes']);
                foreach ($project->expenses->sortBy('spent_on')->values() as $e) {
                    fputcsv($out, [
                        optional($e->spent_on)->format('Y-m-d'),
                        $e->description,
                        $e->category,
                        $e->vendor_ref,
                        optional($e->bankAccount)->name,
                        number_format((float) $e->amount, 2, '.', ''),
                        $e->notes,
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportWorkbook(Project $project): StreamedResponse
    {
        $project->load(['collections.bankAccount', 'expenses.bankAccount', 'allocationLines']);
        $safeName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $project->name) ?: 'project';
        $filename = sprintf('%s-workbook-%s.xlsx', $safeName, now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($project): void {
            $boundary = '----=_NextPart_' . md5((string) microtime(true));
            $eol = "\r\n";

            $sheets = [
                'Allocation' => $this->buildAllocationRows($project),
                'Inflow' => $this->buildInflowRows($project),
                'Outflow' => $this->buildOutflowRows($project),
            ];

            echo "MIME-Version: 1.0{$eol}";
            echo "Content-Type: multipart/related; boundary=\"{$boundary}\"{$eol}{$eol}";

            // Workbook part
            echo "--{$boundary}{$eol}";
            echo "Content-Type: application/xml; charset=\"UTF-8\"{$eol}";
            echo "Content-Location: file:///C:/Book1.xml{$eol}{$eol}";
            echo '<?xml version="1.0"?>' . $eol;
            echo '<?mso-application progid="Excel.Sheet"?>' . $eol;
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
                . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
                . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
                . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
                . $eol;
            echo '<Styles><Style ss:ID="Header"><Font ss:Bold="1"/></Style></Styles>' . $eol;

            foreach ($sheets as $sheetName => $rows) {
                echo '<Worksheet ss:Name="' . e($sheetName) . '"><Table>' . $eol;
                foreach ($rows as $rowIdx => $row) {
                    echo '<Row>' . $eol;
                    foreach ($row as $cell) {
                        $isNumeric = is_numeric($cell);
                        $type = $isNumeric ? 'Number' : 'String';
                        $value = $isNumeric ? (string) $cell : htmlspecialchars((string) $cell, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                        $style = $rowIdx === 0 ? ' ss:StyleID="Header"' : '';
                        echo "<Cell{$style}><Data ss:Type=\"{$type}\">{$value}</Data></Cell>{$eol}";
                    }
                    echo '</Row>' . $eol;
                }
                echo '</Table></Worksheet>' . $eol;
            }

            echo '</Workbook>' . $eol;
            echo "--{$boundary}--{$eol}";
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function buildAllocationRows(Project $project): array
    {
        $rows = [];
        $collectionsChrono = $project->collections->sortBy('collected_on')->values();
        $totalCollected = (float) $project->totalCollected();
        $allocLines = $project->allocationLines;

        $header = ['Category', '%', 'Total Collected'];
        foreach ($collectionsChrono as $idx => $coll) {
            $header[] = sprintf('%d%s Collection (%s)', $idx + 1, $this->ordinalSuffix($idx + 1), $coll->collected_on->format('Y-m-d'));
        }
        $rows[] = $header;

        foreach ($allocLines as $line) {
            if ($line->row_kind === ProjectAllocationLine::KIND_BLANK) {
                $rows[] = [''];
                continue;
            }
            $percent = (float) $line->percent;
            $row = [
                $line->label ?: '',
                round($percent * 100, 2),
                round($totalCollected * $percent, 2),
            ];
            foreach ($collectionsChrono as $coll) {
                $row[] = round((float) $coll->amount * $percent, 2);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function buildInflowRows(Project $project): array
    {
        $rows = [['Date', 'Reference', 'Deposited To', 'Amount', 'Notes']];
        foreach ($project->collections->sortBy('collected_on')->values() as $c) {
            $rows[] = [
                optional($c->collected_on)->format('Y-m-d'),
                $c->reference,
                optional($c->bankAccount)->name,
                (float) $c->amount,
                $c->notes,
            ];
        }
        return $rows;
    }

    private function buildOutflowRows(Project $project): array
    {
        $rows = [['Date', 'Description', 'Category', 'Vendor / Ref', 'Paid From', 'Amount', 'Notes']];
        foreach ($project->expenses->sortBy('spent_on')->values() as $e) {
            $rows[] = [
                optional($e->spent_on)->format('Y-m-d'),
                $e->description,
                $e->category,
                $e->vendor_ref,
                optional($e->bankAccount)->name,
                (float) $e->amount,
                $e->notes,
            ];
        }
        return $rows;
    }

    private function ordinalSuffix(int $number): string
    {
        if (($number % 100) >= 11 && ($number % 100) <= 13) {
            return 'th';
        }
        return match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
