@php
    $budget = (float) $project->contract_value;
    $usedPct = fn ($amount) => $totalCost > 0 ? round($amount / $totalCost * 100, 1) : 0;
@endphp

<x-app-layout :page-title="$project->name">
    <x-project-shell :project="$project" :bank-accounts="$bankAccounts" :collections-chrono="$collectionsChrono" :other-projects="$otherProjects">

        @if ($categorySummary->isEmpty())
            <div class="px-4 py-10 text-center">
                <p class="text-sm text-gray-500">No categories set up yet — manage them under Categories.</p>
            </div>
        @else
        <div class="data-grid overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-slate-50">
                        <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Category</th>
                        <th class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Running Cost</th>
                        <th class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">% of total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categorySummary as $row)
                    <tr class="transition-colors hover:bg-slate-50/70">
                        <td class="font-medium text-slate-700">{{ $row['label'] }}</td>
                        <td class="text-right font-semibold tabular-nums {{ $row['amount'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                            ₱{{ number_format($row['amount'], 2) }}
                        </td>
                        <td class="text-right tabular-nums text-slate-500">
                            {{ $row['amount'] > 0 ? $usedPct($row['amount']) . '%' : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td class="text-[11px] font-bold uppercase tracking-wide text-slate-700">Total running cost</td>
                        <td class="text-right font-bold tabular-nums text-red-600">₱{{ number_format($totalCost, 2) }}</td>
                        <td class="text-right font-bold tabular-nums text-slate-700">{{ $totalCost > 0 ? '100%' : '—' }}</td>
                    </tr>
                    @if ($budget > 0)
                    <tr>
                        <td class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Budget utilization</td>
                        <td class="text-right tabular-nums text-slate-500" colspan="2">
                            ₱{{ number_format($totalCost, 2) }} of ₱{{ number_format($budget, 2) }}
                            ({{ min(100, round($totalCost / $budget * 100, 1)) }}%)
                        </td>
                    </tr>
                    @endif
                </tfoot>
            </table>
        </div>
        @endif

    </x-project-shell>
</x-app-layout>
