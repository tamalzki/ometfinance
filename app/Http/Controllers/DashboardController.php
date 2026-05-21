<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Entity;
use App\Models\LedgerEntry;
use App\Models\Project;
use App\Models\Transfer;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $now           = Carbon::now();
        $monthStart    = $now->copy()->startOfMonth();
        $monthEnd      = $now->copy()->endOfMonth();
        $sixMonthsAgo  = $now->copy()->startOfMonth()->subMonthsNoOverflow(5);

        /* ── Entities + bank cash ────────────────────────────────────────── */
        $entities = Entity::orderBy('sort_order')->with('bankAccounts')->get();

        $accountCount = 0;
        $entityRows   = [];
        foreach ($entities as $entity) {
            $entityTotal = 0.0;
            foreach ($entity->bankAccounts as $account) {
                $bal = $account->currentBalance();
                $entityTotal  += $bal;
                $accountCount += 1;
            }
            $entityRows[] = [
                'name'     => $entity->name,
                'slug'     => $entity->slug,
                'accounts' => $entity->bankAccounts->count(),
                'total'    => (float) $entityTotal,
            ];
        }

        $totalCash = (float) collect($entityRows)->sum('total');

        // Sort entities by total cash desc for the breakdown panel
        $entityRows = collect($entityRows)
            ->sortByDesc('total')
            ->values()
            ->all();

        $maxEntityTotal = max(1, (float) collect($entityRows)->max('total'));

        /* ── This month: real money in / out (transfers excluded) ───────── */
        // Encrypted columns — aggregation handled in PHP. Load all in-scope
        // entries once, then derive month-in / month-out / monthly-flow off
        // the same collection so we only do one round-trip.
        $periodEntries = LedgerEntry::whereNull('transfer_id')
            ->whereBetween('date', [$sixMonthsAgo, $monthEnd])
            ->get();

        $monthEntries = $periodEntries->filter(
            fn ($e) => $e->date->greaterThanOrEqualTo($monthStart)
                && $e->date->lessThanOrEqualTo($monthEnd)
        );
        $monthIn  = (float) $monthEntries->sum(fn ($e) => (float) $e->amount_in);
        $monthOut = (float) $monthEntries->sum(fn ($e) => (float) $e->amount_out);
        $monthNet = $monthIn - $monthOut;

        /* ── This month: transfer activity ──────────────────────────────── */
        $monthTransferCount = Transfer::whereBetween('date', [$monthStart, $monthEnd])->count();
        // Encrypted column — aggregation handled in PHP.
        $monthTransferAmount = (float) Transfer::whereBetween('date', [$monthStart, $monthEnd])
            ->get()
            ->sum(fn ($t) => (float) $t->amount);

        /* ── Project stats ──────────────────────────────────────────────── */
        $activeStatuses = ['active', 'in_progress', 'planning'];

        // Encrypted columns — sums computed in PHP after eager-loading.
        $projects = Project::with(['collections:id,project_id,amount', 'expenses:id,project_id,amount'])->get();
        $projects->each(function ($p): void {
            $p->collected_sum = (float) $p->collections->sum(fn ($c) => (float) $c->amount);
            $p->spent_sum     = (float) $p->expenses->sum(fn ($e) => (float) $e->amount);
        });

        $activeExternal = $projects->where('kind', 'external')->whereIn('status', $activeStatuses)->count();
        $activeInHouse  = $projects->where('kind', 'in_house')->whereIn('status', $activeStatuses)->count();
        $totalActive    = $activeExternal + $activeInHouse;

        $totalCollected = (float) $projects->sum('collected_sum');
        $totalSpent     = (float) $projects->sum('spent_sum');
        $projectNet     = $totalCollected - $totalSpent;

        // Top 5 in-house projects by spending (the "where is the money going" view)
        $topInHouse = $projects
            ->where('kind', 'in_house')
            ->sortByDesc(fn ($p) => (float) $p->spent_sum)
            ->take(5)
            ->values();
        $maxInHouseSpend = max(1, (float) $topInHouse->max(fn ($p) => (float) $p->spent_sum));

        // Top 5 external projects by collection % vs contract
        $topExternal = $projects
            ->where('kind', 'external')
            ->map(function ($p) {
                $collected   = (float) $p->collected_sum;
                $contract    = (float) $p->contract_value;
                $progress    = $contract > 0 ? min(100, ($collected / $contract) * 100) : 0;
                $p->progress = $progress;
                return $p;
            })
            ->sortByDesc('progress')
            ->take(5)
            ->values();

        /* ── Recent activity ────────────────────────────────────────────── */
        $recentTransfers = Transfer::with([
            'fromAccount.entity', 'toAccount.entity',
            'fromProject', 'toProject',
        ])->latest('date')->latest('id')->take(6)->get();

        $recentLedger = LedgerEntry::with('bankAccount.entity')
            ->whereNull('transfer_id')
            ->latest('date')->latest('id')->take(8)->get();

        /* ── Monthly cash flow (last 6 months, transfers excluded) ──────── */
        // Encrypted columns — SUM/GROUP BY done in PHP off $periodEntries
        // loaded earlier in this method (one query for the whole period).
        $monthlyByYm = $periodEntries
            ->groupBy(fn ($e) => $e->date->format('Y-m'))
            ->map(fn ($group) => (object) [
                'sum_in'  => (float) $group->sum(fn ($e) => (float) $e->amount_in),
                'sum_out' => (float) $group->sum(fn ($e) => (float) $e->amount_out),
            ]);

        $monthlyFlow = [];
        for ($i = 0; $i < 6; $i++) {
            $month = $sixMonthsAgo->copy()->addMonthsNoOverflow($i);
            $key   = $month->format('Y-m');
            $row   = $monthlyByYm->get($key);
            $monthlyFlow[] = [
                'label' => $month->format('M'),
                'in'    => (float) ($row->sum_in ?? 0),
                'out'   => (float) ($row->sum_out ?? 0),
            ];
        }

        /* ── Health insights ───────────────────────────────────────────── */
        // Encrypted columns — all comparisons computed in PHP after load.
        $threshold30d = $now->copy()->subDays(30);

        $overBudgetCount = $projects->filter(function ($p) {
            $budget = (float) $p->contract_value;
            $spent  = (float) ($p->spent_sum ?? 0);
            return $p->kind === 'in_house' && $budget > 0 && $spent > $budget;
        })->count();

        $nearingLimitCount = $projects->filter(function ($p) {
            $budget = (float) $p->contract_value;
            $spent  = (float) ($p->spent_sum ?? 0);
            if ($p->kind !== 'in_house' || $budget <= 0) return false;
            $pct = $spent / $budget;
            return $pct >= 0.8 && $pct < 1.0;
        })->count();

        $staleProjectsCount = $projects->filter(function ($p) use ($threshold30d, $activeStatuses) {
            if (! in_array($p->status, $activeStatuses, true)) return false;
            $latest = collect([
                $p->collections->max('collected_on'),
                $p->expenses->max('spent_on'),
            ])->filter()->max();
            return ! $latest || $latest < $threshold30d;
        })->count();

        $overdueExternalCount = $projects->filter(function ($p) use ($now, $activeStatuses) {
            return $p->kind === 'external'
                && in_array($p->status, $activeStatuses, true)
                && $p->due_date
                && $p->due_date < $now;
        })->count();

        $auditEvents7d = AuditLog::where('created_at', '>=', $now->copy()->subDays(7))->count();

        $insights = [
            'over_budget'      => $overBudgetCount,
            'nearing_limit'    => $nearingLimitCount,
            'stale_projects'   => $staleProjectsCount,
            'overdue_external' => $overdueExternalCount,
            'audit_events_7d'  => $auditEvents7d,
        ];

        /* ── Recent audit-log activity (top 6) ─────────────────────────── */
        $recentAudit = AuditLog::with('user:id,name')
            ->latest('id')
            ->take(6)
            ->get();

        return view('dashboard.index', [
            'totals' => [
                'cash'        => $totalCash,
                'accounts'    => $accountCount,
                'entities'    => $entities->count(),
            ],
            'monthSummary' => [
                'in'              => $monthIn,
                'out'             => $monthOut,
                'net'             => $monthNet,
                'transfer_count'  => $monthTransferCount,
                'transfer_amount' => $monthTransferAmount,
                'label'           => $now->format('F Y'),
            ],
            'projectSummary' => [
                'active_total'    => $totalActive,
                'active_external' => $activeExternal,
                'active_in_house' => $activeInHouse,
                'collected'       => $totalCollected,
                'spent'           => $totalSpent,
                'net'             => $projectNet,
            ],
            'entityRows'       => $entityRows,
            'maxEntityTotal'   => $maxEntityTotal,
            'topInHouse'       => $topInHouse,
            'maxInHouseSpend'  => $maxInHouseSpend,
            'topExternal'      => $topExternal,
            'recentTransfers'  => $recentTransfers,
            'recentLedger'     => $recentLedger,
            'monthlyFlow'      => $monthlyFlow,
            'insights'         => $insights,
            'recentAudit'      => $recentAudit,
        ]);
    }
}
