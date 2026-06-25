@php
    $totalCollected  = $project->totalCollected();
    $clientCollected = $project->totalClientCollected();
    $borrowedTotal   = $project->totalBorrowed();
    $totalDeductions = $project->totalDeductions();
    $netCollected    = $project->totalClientCollectedNet();

    $clientRows   = $project->collections->filter(fn ($c) => ! $c->isFromTransfer());
    $borrowedRows = $project->collections->filter(fn ($c) => $c->isFromTransfer());
@endphp

<x-app-layout :page-title="$project->name">
    <x-project-shell :project="$project" :bank-accounts="$bankAccounts" :collections-chrono="$collectionsChrono" :other-projects="$otherProjects">

        @if ($project->collections->isEmpty())
            <div class="px-4 py-12 text-center">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50">
                    <i data-lucide="arrow-down-circle" class="h-5 w-5 text-emerald-600"></i>
                </div>
                <p class="mt-3 text-sm font-medium text-slate-700">No inflows yet</p>
                <p class="mx-auto mt-1 max-w-sm text-[12px] leading-relaxed text-slate-500">
                    Inflows are <span class="font-semibold text-emerald-700">collections</span> from
                    {{ $project->client_name ?: 'the client' }}, or funds
                    <span class="font-semibold text-indigo-700">borrowed</span> from another account or project.
                </p>
                @can('manage-financials')
                <button type="button" @click="showNewCollection = true"
                    class="mt-4 inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow ring-1 ring-emerald-700/20 hover:bg-emerald-700">
                    <i data-lucide="plus-circle" class="h-3.5 w-3.5"></i> Record first inflow
                </button>
                @endcan
            </div>
        @else
        <div x-data="{ inflowFilter: 'all' }" class="space-y-3">

            {{-- Inflow mix + filter chips --}}
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50/60 px-3 py-1.5">
                        <i data-lucide="hand-coins" class="h-3.5 w-3.5 text-emerald-600"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-emerald-800">Collections</span>
                        <span class="text-[13px] font-bold tabular-nums text-emerald-700">₱{{ number_format($clientCollected, 2) }}</span>
                        <span class="text-[11px] tabular-nums text-emerald-600/70">· {{ $clientRows->count() }}</span>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-lg border border-indigo-100 bg-indigo-50/60 px-3 py-1.5">
                        <i data-lucide="arrow-left-right" class="h-3.5 w-3.5 text-indigo-600"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-indigo-800">Borrowed / support</span>
                        <span class="text-[13px] font-bold tabular-nums text-indigo-700">₱{{ number_format($borrowedTotal, 2) }}</span>
                        <span class="text-[11px] tabular-nums text-indigo-600/70">· {{ $borrowedRows->count() }}</span>
                    </div>
                </div>

                <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-[11px] font-medium">
                    <button type="button" @click="inflowFilter = 'all'"
                        :class="inflowFilter === 'all' ? 'bg-omet-navy text-white' : 'text-slate-500 hover:text-omet-navy'"
                        class="cursor-pointer rounded-md px-2.5 py-1 transition-colors duration-150">All</button>
                    <button type="button" @click="inflowFilter = 'collection'"
                        :class="inflowFilter === 'collection' ? 'bg-emerald-600 text-white' : 'text-slate-500 hover:text-emerald-700'"
                        class="cursor-pointer rounded-md px-2.5 py-1 transition-colors duration-150">Collections</button>
                    <button type="button" @click="inflowFilter = 'funding'"
                        :class="inflowFilter === 'funding' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:text-indigo-700'"
                        class="cursor-pointer rounded-md px-2.5 py-1 transition-colors duration-150">Borrowed</button>
                </div>
            </div>

            <div class="data-grid overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Reference</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Deposited to</th>
                            <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Notes</th>
                            <th class="px-4 py-2.5 w-[48px]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($project->collections as $c)
                            @include('projects.external._inflow_row', ['c' => $c, 'showActions' => true, 'showType' => true, 'filterable' => true])
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                        @if ($borrowedRows->isNotEmpty() && $clientRows->isNotEmpty())
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-[11px] uppercase tracking-wide text-slate-400">Collections</td>
                            <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-emerald-700">₱{{ number_format($clientCollected, 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-[11px] uppercase tracking-wide text-slate-400">Borrowed / support</td>
                            <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-indigo-700">₱{{ number_format($borrowedTotal, 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                        @endif
                        @if ($totalDeductions > 0)
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-[11px] uppercase tracking-wide text-amber-600">Deductions (VAT/WHT/retention/recoupment/other)</td>
                            <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-amber-700">−₱{{ number_format($totalDeductions, 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-[11px] uppercase tracking-wide text-slate-400">Real collection (net of deductions)</td>
                            <td class="px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-emerald-700">₱{{ number_format($netCollected, 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                        @endif
                        <tr>
                            <td colspan="4" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total inflow</td>
                            <td class="px-4 py-2.5 text-right text-[13px] font-bold tabular-nums text-emerald-700">₱{{ number_format($totalCollected, 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

    </x-project-shell>
</x-app-layout>
