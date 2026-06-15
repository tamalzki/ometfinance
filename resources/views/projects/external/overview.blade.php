@php
    $totalCollected = $project->totalCollected();
    $contractValue  = (float) $project->contract_value;
    $completionPct  = $contractValue > 0 ? min(100, round($totalCollected / $contractValue * 100, 1)) : 0;
    $barWidth       = $contractValue > 0 ? min(100, $completionPct) : 0;

    $today      = \Illuminate\Support\Carbon::today();
    $dueDate    = $project->due_date;
    $dueStatus  = null;
    $dueBadge   = '';
    if ($dueDate) {
        $diffDays = $today->diffInDays($dueDate, false);
        if ($diffDays < 0) {
            $dueStatus = 'Overdue · ' . abs($diffDays) . 'd';
            $dueBadge  = 'bg-red-100 text-red-700';
        } elseif ($diffDays <= 14) {
            $dueStatus = 'Due in ' . $diffDays . 'd';
            $dueBadge  = 'bg-amber-100 text-amber-800';
        } else {
            $dueStatus = 'On track';
            $dueBadge  = 'bg-green-100 text-green-700';
        }
    }

    $recentInflows  = $project->collections->sortByDesc('collected_on')->take(5);
    $recentOutflows = $project->expenses->sortByDesc('spent_on')->take(5);
@endphp

<x-app-layout :page-title="$project->name">
    <x-project-shell :project="$project" :bank-accounts="$bankAccounts" :collections-chrono="$collectionsChrono" :other-projects="$otherProjects">

        <div class="space-y-4 p-4">

            {{-- Progress + due date callout --}}
            <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm">
                <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Collection progress</h3>
                            @if ($dueStatus)
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $dueBadge }}">{{ $dueStatus }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-600">
                            @if ($contractValue > 0)
                                <span class="font-bold tabular-nums text-emerald-700">₱{{ number_format($totalCollected, 2) }}</span>
                                <span class="text-slate-400">collected of</span>
                                <span class="font-semibold tabular-nums text-omet-navy">₱{{ number_format($contractValue, 2) }}</span>
                                <span class="text-slate-400">contract value</span>
                            @else
                                <span class="text-slate-400">No contract value set — add one in Edit project to track progress.</span>
                            @endif
                        </p>
                        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                            <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all"
                                 style="width: {{ $barWidth }}%"></div>
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-3xl font-bold tabular-nums text-omet-navy">
                            {{ $contractValue > 0 ? $completionPct : 0 }}<span class="text-base text-slate-400">%</span>
                        </p>
                        @if ($dueDate)
                            <p class="text-[11px] text-slate-500">Due {{ $dueDate->format('M j, Y') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Recent activity panels --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                {{-- Recent inflows --}}
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-emerald-100 bg-emerald-50/60 px-4 py-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-800">
                            <i data-lucide="arrow-down-circle" class="mr-1 inline h-3.5 w-3.5 align-text-bottom"></i>
                            Recent inflows
                        </h3>
                        <a href="{{ route('projects.show.inflow', $project) }}"
                           class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-800">
                            View all →
                        </a>
                    </div>
                    @if ($recentInflows->isEmpty())
                        <div class="px-4 py-6 text-center">
                            <p class="text-[11px] text-gray-500">No inflows yet. Use the green <strong>Inflow</strong> button above to record one.</p>
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
                            <p class="text-[11px] text-gray-500">No outflows yet. Use the red <strong>Outflow</strong> button above to record one.</p>
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
