@php
    $totalCollected = $project->totalCollected();
    $totalSpent     = $project->totalExpenses();
    $budget         = (float) $project->contract_value;
    $hasBudget      = $budget > 0;
    $usedPct        = $hasBudget ? min(100, round($totalSpent / $budget * 100, 1)) : 0;
    $remaining      = $hasBudget ? $budget - $totalSpent : null;
    $entryCount     = $project->collections->count() + $project->expenses->count();

    $allDates = $project->collections->pluck('collected_on')
        ->concat($project->expenses->pluck('spent_on'))
        ->filter()
        ->sort()
        ->values();
    $firstDate = $allDates->first();
    $lastDate  = $allDates->last();

    $recentInflows  = $project->collections->sortByDesc('collected_on')->take(5);
    $recentOutflows = $project->expenses->sortByDesc('spent_on')->take(5);

    $today = \Illuminate\Support\Carbon::today();
    $overBudget = $hasBudget && $totalSpent > $budget;
@endphp

<x-app-layout :page-title="$project->name">
    <x-project-shell :project="$project" :bank-accounts="$bankAccounts" :collections-chrono="$collectionsChrono" :other-projects="$otherProjects">

        <div class="space-y-4 p-4">

            {{-- Budget / Spend callout --}}
            <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm">
                <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                {{ $hasBudget ? 'Budget utilization' : 'Total spent' }}
                            </h3>
                            @if ($overBudget)
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700">Over budget</span>
                            @elseif ($hasBudget && $usedPct >= 80)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Nearing limit</span>
                            @elseif ($hasBudget)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700">On track</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-600">
                            @if ($hasBudget)
                                <span class="font-bold tabular-nums text-red-600">₱{{ number_format($totalSpent, 2) }}</span>
                                <span class="text-slate-400">spent of</span>
                                <span class="font-semibold tabular-nums text-omet-navy">₱{{ number_format($budget, 2) }}</span>
                                <span class="text-slate-400">budget · </span>
                                <span class="font-semibold tabular-nums {{ $remaining < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                    ₱{{ number_format(abs($remaining), 2) }} {{ $remaining < 0 ? 'over' : 'remaining' }}
                                </span>
                            @else
                                <span class="font-bold tabular-nums text-omet-navy">₱{{ number_format($totalSpent, 2) }}</span>
                                <span class="text-slate-400">across {{ $entryCount }} {{ $entryCount === 1 ? 'entry' : 'entries' }}</span>
                                @if ($firstDate && $lastDate)
                                    <span class="text-slate-400">· {{ $firstDate->format('M j, Y') }} → {{ $lastDate->format('M j, Y') }}</span>
                                @endif
                            @endif
                        </p>
                        @if ($hasBudget)
                            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full transition-all {{ $overBudget ? 'bg-gradient-to-r from-red-500 to-red-600' : ($usedPct >= 80 ? 'bg-gradient-to-r from-amber-400 to-amber-500' : 'bg-gradient-to-r from-emerald-400 to-emerald-600') }}"
                                     style="width: {{ min(100, $usedPct) }}%"></div>
                            </div>
                        @else
                            <p class="mt-3 rounded-md border border-dashed border-slate-200 bg-white px-3 py-2 text-[11px] text-slate-500">
                                <i data-lucide="info" class="mr-1 inline h-3 w-3 align-text-bottom"></i>
                                Set a budget in <strong>Edit project</strong> to track utilization.
                            </p>
                        @endif
                    </div>
                    <div class="shrink-0 text-right">
                        @if ($hasBudget)
                            <p class="text-3xl font-bold tabular-nums {{ $overBudget ? 'text-red-600' : 'text-omet-navy' }}">
                                {{ $usedPct }}<span class="text-base text-slate-400">%</span>
                            </p>
                            <p class="text-[11px] text-slate-500">of budget used</p>
                        @else
                            <p class="text-3xl font-bold tabular-nums text-omet-navy">
                                {{ $entryCount }}
                            </p>
                            <p class="text-[11px] text-slate-500">{{ $entryCount === 1 ? 'entry' : 'entries' }} recorded</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Recent activity panels --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                {{-- Recent funding (borrowed from accounts / project support) --}}
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-emerald-100 bg-emerald-50/60 px-4 py-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-800">
                            <i data-lucide="banknote" class="mr-1 inline h-3.5 w-3.5 align-text-bottom"></i>
                            Recent funding
                        </h3>
                        <a href="{{ route('projects.show.inflow', $project) }}"
                           class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-800">
                            View all →
                        </a>
                    </div>
                    @if ($recentInflows->isEmpty())
                        <div class="px-4 py-6 text-center">
                            <p class="text-[11px] text-gray-500">No funding recorded yet. Use the green <strong>Funding</strong> button above to borrow from another account or take support from another project.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100">
                                <thead>
                                    <tr class="bg-slate-50">
                                        <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">Date</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">Reference</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">Deposited to</th>
                                        <th class="px-4 py-2 text-right text-[10px] font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($recentInflows as $c)
                                        @include('projects.external._inflow_row', ['c' => $c, 'showActions' => false])
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Recent outflows --}}
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-red-100 bg-red-50/60 px-4 py-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-red-800">
                            <i data-lucide="arrow-up-circle" class="mr-1 inline h-3.5 w-3.5 align-text-bottom"></i>
                            Recent outflows
                        </h3>
                        <a href="{{ route('projects.show.outflow', $project) }}"
                           class="text-[11px] font-semibold text-red-700 hover:text-red-800">
                            View all →
                        </a>
                    </div>
                    @if ($recentOutflows->isEmpty())
                        <div class="px-4 py-6 text-center">
                            <p class="text-[11px] text-gray-500">No outflows yet. Use the red <strong>Outflow</strong> button above to record an expense (PO, vendor, payroll).</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100">
                                <thead>
                                    <tr class="bg-slate-50">
                                        <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">Date</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">Description</th>
                                        <th class="px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">Category</th>
                                        <th class="px-4 py-2 text-right text-[10px] font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($recentOutflows as $e)
                                        @include('projects.external._outflow_row', ['e' => $e, 'showActions' => false])
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

            </div>

        </div>

    </x-project-shell>
</x-app-layout>
