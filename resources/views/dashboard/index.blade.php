<x-app-layout page-title="Dashboard">
@php
    $peso = fn ($n) => '₱' . number_format((float) $n, 2);
    $pesoShort = function ($n) {
        $v = (float) $n;
        $abs = abs($v);
        if ($abs >= 1_000_000) return '₱' . number_format($v / 1_000_000, 2) . 'M';
        if ($abs >= 1_000)     return '₱' . number_format($v / 1_000, 1) . 'K';
        return '₱' . number_format($v, 0);
    };
    $isCfo = auth()->user()->isCfo();
@endphp

@if ($isCfo)
{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- CFO DASHBOARD — disbursements-focused view                           --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="flex flex-col gap-5">

    {{-- Heading --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Disbursements overview</h1>
            <p class="mt-0.5 text-sm text-slate-500">Live payables status and recent disbursement activity.</p>
        </div>
        <p class="text-xs font-medium text-slate-500">As of {{ now()->format('M d, Y · g:i A') }}</p>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Outstanding payables --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Outstanding payables</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                    <i data-lucide="file-text" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight text-slate-900">{{ $peso($payables['outstanding']) }}</p>
            <p class="mt-1 text-[11.5px] text-slate-500">Total unpaid balance</p>
        </div>

        {{-- Overdue --}}
        @php $hasOverdue = $insights['payables_overdue'] > 0; @endphp
        <div class="rounded-xl border {{ $hasOverdue ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-white' }} p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider {{ $hasOverdue ? 'text-rose-600' : 'text-slate-500' }}">Overdue</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $hasOverdue ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-500' }}">
                    <i data-lucide="alarm-clock" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight {{ $hasOverdue ? 'text-rose-700' : 'text-slate-900' }}">
                {{ $insights['payables_overdue'] }}
                <span class="text-base font-medium">voucher{{ $insights['payables_overdue'] !== 1 ? 's' : '' }}</span>
            </p>
            <p class="mt-1 text-[11.5px] {{ $hasOverdue ? 'text-rose-600' : 'text-slate-500' }}">
                {{ $hasOverdue ? $peso($payables['overdue_amt']) . ' past due' : 'No overdue payables' }}
            </p>
        </div>

        {{-- Due in 7 days --}}
        @php $hasDue7 = $insights['payables_due_7d'] > 0; @endphp
        <div class="rounded-xl border {{ $hasDue7 ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white' }} p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider {{ $hasDue7 ? 'text-amber-700' : 'text-slate-500' }}">Due in 7 days</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $hasDue7 ? 'bg-amber-100 text-amber-600' : 'bg-slate-100 text-slate-500' }}">
                    <i data-lucide="hourglass" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight {{ $hasDue7 ? 'text-amber-800' : 'text-slate-900' }}">
                {{ $insights['payables_due_7d'] }}
                <span class="text-base font-medium">voucher{{ $insights['payables_due_7d'] !== 1 ? 's' : '' }}</span>
            </p>
            <p class="mt-1 text-[11.5px] {{ $hasDue7 ? 'text-amber-700' : 'text-slate-500' }}">
                {{ $hasDue7 ? $peso($payables['due_7d_amt']) . ' coming due' : 'Nothing due soon' }}
            </p>
        </div>

        {{-- Paid out this month --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    Paid out — {{ $monthSummary['label'] }}
                </p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                    <i data-lucide="send" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight text-slate-900">{{ $peso($monthSummary['out']) }}</p>
            <p class="mt-1 text-[11.5px] text-slate-500">Total disbursements this month</p>
        </div>
    </div>

    {{-- Needs attention + Quick reports --}}
    @php
        $payablesHasInsights = ($insights['payables_overdue'] + $insights['payables_due_7d']) > 0;
    @endphp
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_260px]">

        {{-- Payables alerts --}}
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
            <span class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Needs attention</span>

            @if ($insights['payables_overdue'] > 0)
            <a href="{{ route('vouchers.payables', ['bucket' => 'd1_30']) }}"
               class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-rose-100 transition hover:bg-rose-100">
                <i data-lucide="alarm-clock" class="h-3 w-3"></i>
                {{ $insights['payables_overdue'] }} payables overdue · {{ $pesoShort($payables['overdue_amt']) }}
            </a>
            @endif

            @if ($insights['payables_due_7d'] > 0)
            <a href="{{ route('vouchers.payables') }}"
               class="inline-flex items-center gap-1 rounded-md bg-orange-50 px-2 py-0.5 text-[11px] font-semibold text-orange-800 ring-1 ring-orange-100 transition hover:bg-orange-100">
                <i data-lucide="hourglass" class="h-3 w-3"></i>
                {{ $insights['payables_due_7d'] }} due in 7 days · {{ $pesoShort($payables['due_7d_amt']) }}
            </a>
            @endif

            @unless ($payablesHasInsights)
            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-700">
                <i data-lucide="check-circle-2" class="h-3 w-3"></i>
                All payables look healthy
            </span>
            @endunless
        </div>

        {{-- Quick reports (CFO-accessible only) --}}
        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
            <p class="mb-1.5 text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Quick reports</p>
            <div class="flex flex-wrap gap-1.5">
                <a href="{{ route('reports') }}"
                   class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 hover:border-omet-blue hover:text-omet-blue">
                    <i data-lucide="gauge" class="h-3 w-3"></i> Overall
                </a>
                <a href="{{ route('reports.cashOutflow') }}"
                   class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 hover:border-omet-blue hover:text-omet-blue">
                    <i data-lucide="arrow-up-circle" class="h-3 w-3"></i> Outflow
                </a>
                <a href="{{ route('reports.payables') }}"
                   class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 hover:border-omet-blue hover:text-omet-blue">
                    <i data-lucide="alarm-clock" class="h-3 w-3"></i> Payables
                </a>
            </div>
        </div>
    </div>

    {{-- Monthly disbursements chart + System activity --}}
    <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1fr)_360px]">

        {{-- Monthly cash outflow chart --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Monthly disbursements</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Cash paid out vs collected — last 6 months.</p>
                </div>
                <div class="flex items-center gap-4 text-[11px] text-slate-500">
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-emerald-500"></span> Inflow</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-rose-500"></span> Outflow</span>
                </div>
            </div>
            <div class="relative h-[260px]">
                <canvas id="cashFlowChart"></canvas>
            </div>
        </div>

        {{-- System activity audit log --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-end justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Recent activity</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Latest voucher changes.</p>
                </div>
                <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    <i data-lucide="shield-check" class="h-3 w-3"></i> Audited
                </span>
            </div>

            @if ($recentAudit->isEmpty())
                <p class="rounded-md border border-dashed border-slate-200 px-4 py-6 text-center text-xs text-slate-500">
                    No activity recorded yet.
                </p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentAudit as $a)
                        @php
                            $eventColor = match ($a->event) {
                                'created' => 'bg-emerald-50 text-emerald-600',
                                'updated' => 'bg-blue-50 text-blue-600',
                                'deleted' => 'bg-rose-50 text-rose-600',
                                default   => 'bg-slate-100 text-slate-500',
                            };
                            $eventIcon = match ($a->event) {
                                'created' => 'plus-circle',
                                'updated' => 'edit-3',
                                'deleted' => 'trash-2',
                                default   => 'circle',
                            };
                        @endphp
                        <li class="flex items-start gap-3 py-2.5">
                            <span class="mt-1 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md {{ $eventColor }}">
                                <i data-lucide="{{ $eventIcon }}" class="h-3.5 w-3.5"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] text-slate-800">
                                    <span class="capitalize font-medium">{{ $a->event }}</span>
                                    <span class="text-slate-400"> · </span>
                                    {{ class_basename($a->auditable_type) }} #{{ $a->auditable_id }}
                                </p>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">
                                    {{ $a->user?->name ?? 'System' }} · {{ $a->created_at?->diffForHumans() }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('cashFlowChart');
        if (!ctx || typeof Chart === 'undefined') return;
        const labels = @json(array_column($monthlyFlow, 'label'));
        const inflow  = @json(array_column($monthlyFlow, 'in'));
        const outflow = @json(array_column($monthlyFlow, 'out'));
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Inflow',  data: inflow,  backgroundColor: '#10b981', borderRadius: 4, maxBarThickness: 28 },
                    { label: 'Outflow', data: outflow, backgroundColor: '#ef4444', borderRadius: 4, maxBarThickness: 28 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) {
                    return ctx.dataset.label + ': ₱' + (ctx.parsed.y || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
                }}}},
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 11 } } },
                    y: { grid: { color: '#f1f5f9' }, border: { display: false }, ticks: { color: '#64748b', font: { size: 11 }, callback: function (v) {
                        if (Math.abs(v) >= 1_000_000) return '₱' + (v / 1_000_000).toFixed(1) + 'M';
                        if (Math.abs(v) >= 1_000) return '₱' + (v / 1_000).toFixed(0) + 'K';
                        return '₱' + v;
                    }}},
                },
            },
        });
    });
</script>

@else
{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- ADMIN DASHBOARD — full finance overview                              --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="flex flex-col gap-5">

    {{-- ── Heading ────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Finance overview</h1>
            <p class="mt-0.5 text-sm text-slate-500">A live look at cash, money movement, and project performance.</p>
        </div>
        <p class="text-xs font-medium text-slate-500">As of {{ now()->format('M d, Y · g:i A') }}</p>
    </div>

    {{-- ── Top KPIs ───────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Total cash --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Cash on hand</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                    <i data-lucide="wallet" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight text-slate-900">{{ $peso($totals['cash']) }}</p>
            <p class="mt-1 text-[11.5px] text-slate-500">
                Across {{ $totals['accounts'] }} {{ \Illuminate\Support\Str::plural('account', $totals['accounts']) }}
                in {{ $totals['entities'] }} {{ \Illuminate\Support\Str::plural('entity', $totals['entities']) }}
            </p>
        </div>

        {{-- Net this month --}}
        @php
            $isPositive = $monthSummary['net'] >= 0;
        @endphp
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    Net cash — {{ $monthSummary['label'] }}
                </p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg
                    {{ $isPositive ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                    <i data-lucide="{{ $isPositive ? 'trending-up' : 'trending-down' }}" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight {{ $isPositive ? 'text-slate-900' : 'text-rose-600' }}">
                {{ ($isPositive ? '' : '−') . $peso(abs($monthSummary['net'])) }}
            </p>
            <p class="mt-1 text-[11.5px] text-slate-500">
                In <span class="font-semibold text-emerald-700">{{ $peso($monthSummary['in']) }}</span>
                · Out <span class="font-semibold text-rose-600">{{ $peso($monthSummary['out']) }}</span>
            </p>
        </div>

        {{-- Active projects --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Active projects</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                    <i data-lucide="folder-kanban" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight text-slate-900">{{ $projectSummary['active_total'] }}</p>
            <p class="mt-1 text-[11.5px] text-slate-500">
                {{ $projectSummary['active_external'] }} external · {{ $projectSummary['active_in_house'] }} in-house
            </p>
        </div>

        {{-- Project cash position --}}
        @php $projectNetPositive = $projectSummary['net'] >= 0; @endphp
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Project cash position</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                    <i data-lucide="scale" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight {{ $projectNetPositive ? 'text-slate-900' : 'text-rose-600' }}">
                {{ ($projectNetPositive ? '' : '−') . $peso(abs($projectSummary['net'])) }}
            </p>
            <p class="mt-1 text-[11.5px] text-slate-500">
                Collected <span class="font-semibold text-emerald-700">{{ $peso($projectSummary['collected']) }}</span>
                · Spent <span class="font-semibold text-rose-600">{{ $peso($projectSummary['spent']) }}</span>
            </p>
        </div>
    </div>

    {{-- ── Health insights strip + quick reports ─────────────────────────── --}}
    @php
        $hasInsights = ($insights['over_budget'] + $insights['nearing_limit'] + $insights['stale_projects']
            + $insights['overdue_external'] + $insights['payables_overdue'] + $insights['payables_due_7d']) > 0;
    @endphp
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
        {{-- Insights --}}
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
            <span class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Needs attention</span>

            @if ($insights['payables_overdue'] > 0)
            <a href="{{ route('vouchers.payables', ['bucket' => 'd1_30']) }}"
               class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-rose-100 transition hover:bg-rose-100">
                <i data-lucide="alarm-clock" class="h-3 w-3"></i>
                {{ $insights['payables_overdue'] }} payables overdue · {{ $pesoShort($payables['overdue_amt']) }}
            </a>
            @endif

            @if ($insights['payables_due_7d'] > 0)
            <a href="{{ route('vouchers.payables') }}"
               class="inline-flex items-center gap-1 rounded-md bg-orange-50 px-2 py-0.5 text-[11px] font-semibold text-orange-800 ring-1 ring-orange-100 transition hover:bg-orange-100">
                <i data-lucide="hourglass" class="h-3 w-3"></i>
                {{ $insights['payables_due_7d'] }} due in 7 days · {{ $pesoShort($payables['due_7d_amt']) }}
            </a>
            @endif

            @if ($insights['over_budget'] > 0)
            <span class="inline-flex items-center gap-1 rounded-md bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700 ring-1 ring-red-100">
                <i data-lucide="alert-triangle" class="h-3 w-3"></i>
                {{ $insights['over_budget'] }} over budget
            </span>
            @endif

            @if ($insights['nearing_limit'] > 0)
            <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-100">
                <i data-lucide="alert-circle" class="h-3 w-3"></i>
                {{ $insights['nearing_limit'] }} nearing limit
            </span>
            @endif

            @if ($insights['overdue_external'] > 0)
            <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-rose-100">
                <i data-lucide="flag" class="h-3 w-3"></i>
                {{ $insights['overdue_external'] }} overdue (external)
            </span>
            @endif

            @if ($insights['stale_projects'] > 0)
            <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200">
                <i data-lucide="moon" class="h-3 w-3"></i>
                {{ $insights['stale_projects'] }} stale
            </span>
            @endif

            @unless ($hasInsights)
            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-700">
                <i data-lucide="check-circle-2" class="h-3 w-3"></i>
                All active projects look healthy
            </span>
            @endunless

            <span class="ml-auto inline-flex items-center gap-1 text-[10.5px] text-slate-400">
                <i data-lucide="activity" class="h-3 w-3"></i>
                {{ $insights['audit_events_7d'] }} system events · last 7d
            </span>
        </div>

        {{-- Quick reports --}}
        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
            <p class="mb-1.5 text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Quick reports</p>
            <div class="flex flex-wrap gap-1.5">
                <a href="{{ route('reports') }}"
                   class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 hover:border-omet-blue hover:text-omet-blue">
                    <i data-lucide="gauge" class="h-3 w-3"></i> Overall
                </a>
                <a href="{{ route('reports.accountBalances') }}"
                   class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 hover:border-omet-blue hover:text-omet-blue">
                    <i data-lucide="landmark" class="h-3 w-3"></i> Balances
                </a>
                <a href="{{ route('reports.cashOutflow') }}"
                   class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 hover:border-omet-blue hover:text-omet-blue">
                    <i data-lucide="arrow-up-circle" class="h-3 w-3"></i> Outflow
                </a>
                <a href="{{ route('reports.transfers') }}"
                   class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 hover:border-omet-blue hover:text-omet-blue">
                    <i data-lucide="arrow-left-right" class="h-3 w-3"></i> Transfers
                </a>
                <a href="{{ route('reports.collections') }}"
                   class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 hover:border-omet-blue hover:text-omet-blue">
                    <i data-lucide="arrow-down-circle" class="h-3 w-3"></i> Collections
                </a>
                <a href="{{ route('reports.payables') }}"
                   class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 hover:border-omet-blue hover:text-omet-blue">
                    <i data-lucide="alarm-clock" class="h-3 w-3"></i> Payables
                </a>
            </div>
        </div>
    </div>

    {{-- ── Monthly cash flow + Entity breakdown ──────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1fr)_360px]">

        {{-- Monthly cash flow chart --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Monthly cash flow</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Real money in vs out — last 6 months. Internal transfers excluded.</p>
                </div>
                <div class="flex items-center gap-4 text-[11px] text-slate-500">
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-emerald-500"></span> Inflow</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-rose-500"></span> Outflow</span>
                </div>
            </div>
            <div class="relative h-[260px]">
                <canvas id="cashFlowChart"></canvas>
            </div>
        </div>

        {{-- Cash by entity --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-end justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Cash by entity</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Live balances per business.</p>
                </div>
                <a href="{{ route('accounts.overall') }}" class="text-xs font-semibold text-omet-blue hover:text-omet-lightblue">View all</a>
            </div>

            @if (empty($entityRows))
                <p class="rounded-md border border-dashed border-slate-200 px-4 py-6 text-center text-xs text-slate-500">
                    No entities configured yet.
                </p>
            @else
                <div class="space-y-3">
                    @foreach ($entityRows as $row)
                        @php
                            $share = $maxEntityTotal > 0 ? min(100, max(2, ($row['total'] / $maxEntityTotal) * 100)) : 0;
                        @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-[12.5px]">
                                <p class="truncate font-medium text-slate-700">{{ $row['name'] }}</p>
                                <p class="shrink-0 font-semibold tabular-nums text-slate-900">{{ $pesoShort($row['total']) }}</p>
                            </div>
                            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <span class="block h-full rounded-full bg-omet-blue" style="width: {{ $share }}%"></span>
                            </div>
                            <p class="mt-1 text-[10.5px] text-slate-500">
                                {{ $row['accounts'] }} {{ \Illuminate\Support\Str::plural('account', $row['accounts']) }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ── Project performance row ───────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 xl:grid-cols-2">

        {{-- Top in-house projects by spending --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-end justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Top in-house projects</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Ranked by total outflow.</p>
                </div>
                <a href="{{ route('projects.in_house') }}" class="text-xs font-semibold text-omet-blue hover:text-omet-lightblue">View all</a>
            </div>

            @if ($topInHouse->isEmpty())
                <p class="rounded-md border border-dashed border-slate-200 px-4 py-6 text-center text-xs text-slate-500">
                    No in-house projects yet.
                </p>
            @else
                <div class="space-y-3.5">
                    @foreach ($topInHouse as $p)
                        @php
                            $spent = (float) ($p->spent_sum ?? 0);
                            $share = $maxInHouseSpend > 0 ? min(100, max(2, ($spent / $maxInHouseSpend) * 100)) : 0;
                        @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-[12.5px]">
                                <a href="{{ route('projects.show', $p) }}" class="truncate font-medium text-slate-800 hover:text-omet-blue">
                                    {{ $p->name }}
                                </a>
                                <p class="shrink-0 font-semibold tabular-nums text-rose-600">{{ $pesoShort($spent) }}</p>
                            </div>
                            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <span class="block h-full rounded-full bg-rose-500" style="width: {{ $share }}%"></span>
                            </div>
                            <p class="mt-1 text-[10.5px] text-slate-500">
                                {{ $p->client_name ?: 'Internal' }} · status {{ $p->status }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- External project progress --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-end justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">External project progress</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Collected vs contract value.</p>
                </div>
                <a href="{{ route('projects.external') }}" class="text-xs font-semibold text-omet-blue hover:text-omet-lightblue">View all</a>
            </div>

            @if ($topExternal->isEmpty())
                <p class="rounded-md border border-dashed border-slate-200 px-4 py-6 text-center text-xs text-slate-500">
                    No external projects yet.
                </p>
            @else
                <div class="space-y-3.5">
                    @foreach ($topExternal as $p)
                        @php
                            $collected = (float) ($p->collected_sum ?? 0);
                            $contract  = (float) $p->contract_value;
                            $progress  = (float) ($p->progress ?? 0);
                        @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-[12.5px]">
                                <a href="{{ route('projects.show', $p) }}" class="truncate font-medium text-slate-800 hover:text-omet-blue">
                                    {{ $p->name }}
                                </a>
                                <p class="shrink-0 font-semibold tabular-nums text-slate-900">{{ number_format($progress, 1) }}%</p>
                            </div>
                            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <span class="block h-full rounded-full bg-emerald-500" style="width: {{ max(2, min(100, $progress)) }}%"></span>
                            </div>
                            <p class="mt-1 text-[10.5px] text-slate-500">
                                {{ $pesoShort($collected) }} of {{ $pesoShort($contract) }} · {{ $p->client_name ?: 'Client TBA' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ── Activity row ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 xl:grid-cols-3">

        {{-- Recent system activity (audit log) --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-end justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">System activity</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Latest audit-logged changes.</p>
                </div>
                <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    <i data-lucide="shield-check" class="h-3 w-3"></i>
                    Audited
                </span>
            </div>

            @if ($recentAudit->isEmpty())
                <p class="rounded-md border border-dashed border-slate-200 px-4 py-6 text-center text-xs text-slate-500">
                    No activity recorded yet.
                </p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentAudit as $a)
                        @php
                            $eventColor = match ($a->event) {
                                'created' => 'bg-emerald-50 text-emerald-600',
                                'updated' => 'bg-blue-50 text-blue-600',
                                'deleted' => 'bg-rose-50 text-rose-600',
                                default   => 'bg-slate-100 text-slate-500',
                            };
                            $eventIcon = match ($a->event) {
                                'created' => 'plus-circle',
                                'updated' => 'edit-3',
                                'deleted' => 'trash-2',
                                default   => 'circle',
                            };
                        @endphp
                        <li class="flex items-start gap-3 py-2.5">
                            <span class="mt-1 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md {{ $eventColor }}">
                                <i data-lucide="{{ $eventIcon }}" class="h-3.5 w-3.5"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] text-slate-800">
                                    <span class="capitalize font-medium">{{ $a->event }}</span>
                                    <span class="text-slate-400"> · </span>
                                    {{ class_basename($a->auditable_type) }} #{{ $a->auditable_id }}
                                </p>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">
                                    {{ $a->user?->name ?? 'System' }} · {{ $a->created_at?->diffForHumans() }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Recent transfers --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-end justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Recent transfers</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $monthSummary['transfer_count'] }} this month · {{ $pesoShort($monthSummary['transfer_amount']) }} moved
                    </p>
                </div>
                <a href="{{ route('transfers.index') }}" class="text-xs font-semibold text-omet-blue hover:text-omet-lightblue">View all</a>
            </div>

            @if ($recentTransfers->isEmpty())
                <p class="rounded-md border border-dashed border-slate-200 px-4 py-6 text-center text-xs text-slate-500">
                    No transfers recorded yet.
                </p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentTransfers as $t)
                        <li class="flex items-start gap-3 py-2.5">
                            <span class="mt-1 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md
                                {{ $t->isIntercompany() ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-slate-500' }}">
                                <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] text-slate-800">
                                    {{ $t->fromAccount?->name ?? '—' }}
                                    <span class="text-slate-400">→</span>
                                    {{ $t->toAccount?->name ?? '—' }}
                                </p>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">
                                    {{ $t->date->format('M d, Y') }} · {{ $t->purposeLabel() }}
                                    @if ($t->hasProjectImpact())
                                        ·
                                        @if ($t->fromProject) from <span class="font-medium text-rose-600">{{ $t->fromProject->name }}</span>@endif
                                        @if ($t->toProject) to <span class="font-medium text-emerald-700">{{ $t->toProject->name }}</span>@endif
                                    @endif
                                </p>
                            </div>
                            <p class="shrink-0 text-[13px] font-semibold tabular-nums text-slate-900">
                                {{ $pesoShort($t->amount) }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Recent ledger entries (manual, non-transfer) --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-end justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Recent bank activity</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Non-transfer ledger entries.</p>
                </div>
                <a href="{{ route('accounts.overall') }}" class="text-xs font-semibold text-omet-blue hover:text-omet-lightblue">Open accounts</a>
            </div>

            @if ($recentLedger->isEmpty())
                <p class="rounded-md border border-dashed border-slate-200 px-4 py-6 text-center text-xs text-slate-500">
                    No bank activity yet.
                </p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentLedger as $entry)
                        @php
                            $isIn = (float) $entry->amount_in > 0;
                            $amount = $isIn ? (float) $entry->amount_in : (float) $entry->amount_out;
                        @endphp
                        <li class="flex items-start gap-3 py-2.5">
                            <span class="mt-1 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md
                                {{ $isIn ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                                <i data-lucide="{{ $isIn ? 'arrow-down-left' : 'arrow-up-right' }}" class="h-3.5 w-3.5"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] text-slate-800">{{ $entry->description ?: 'Untitled entry' }}</p>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">
                                    {{ $entry->date->format('M d, Y') }} ·
                                    {{ $entry->bankAccount?->name }}
                                    @if ($entry->bankAccount?->entity?->name)
                                        <span class="text-slate-400">({{ $entry->bankAccount->entity->name }})</span>
                                    @endif
                                </p>
                            </div>
                            <p class="shrink-0 text-[13px] font-semibold tabular-nums {{ $isIn ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ ($isIn ? '+' : '−') . $pesoShort($amount) }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('cashFlowChart');
        if (!ctx || typeof Chart === 'undefined') return;

        const labels = @json(array_column($monthlyFlow, 'label'));
        const inflow  = @json(array_column($monthlyFlow, 'in'));
        const outflow = @json(array_column($monthlyFlow, 'out'));
        {{-- Admin chart shares the same canvas id --}}

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Inflow',
                        data: inflow,
                        backgroundColor: '#10b981',
                        borderRadius: 4,
                        maxBarThickness: 28,
                    },
                    {
                        label: 'Outflow',
                        data: outflow,
                        backgroundColor: '#ef4444',
                        borderRadius: 4,
                        maxBarThickness: 28,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const v = ctx.parsed.y || 0;
                                return ctx.dataset.label + ': ₱' + v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b', font: { size: 11 } },
                    },
                    y: {
                        grid: { color: '#f1f5f9' },
                        border: { display: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 11 },
                            callback: function (v) {
                                if (Math.abs(v) >= 1_000_000) return '₱' + (v / 1_000_000).toFixed(1) + 'M';
                                if (Math.abs(v) >= 1_000) return '₱' + (v / 1_000).toFixed(0) + 'K';
                                return '₱' + v;
                            },
                        },
                    },
                },
            },
        });
    });
</script>
@endif
</x-app-layout>
