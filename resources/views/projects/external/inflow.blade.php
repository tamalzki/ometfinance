@php
    $totalCollected = $project->totalCollected();
    $isExternal     = $project->isExternal();
    $refLabel       = $isExternal ? 'Reference' : 'Loan reference';
    $depositedLabel = $isExternal ? 'Deposited to' : 'Disbursed to';
    $totalLabel     = $isExternal ? 'Total inflow' : 'Total funded';
    $notesLabel     = $isExternal ? 'Notes' : 'Lender / notes';
    $buttonLabel    = $isExternal ? 'Inflow' : 'Funding';
    $emptyCopy      = $isExternal
        ? 'No inflows yet. Use the green <strong>Inflow</strong> button above to record the first payment received.'
        : 'No funding recorded yet. Use the green <strong>Funding</strong> button above to log the first loan disbursement.';
@endphp

<x-app-layout :page-title="$project->name">
    <x-project-shell :project="$project" :bank-accounts="$bankAccounts" :collections-chrono="$collectionsChrono">

        @if ($project->collections->isEmpty())
            <div class="px-4 py-10 text-center">
                <p class="text-sm text-gray-500">{!! $emptyCopy !!}</p>
            </div>
        @else
        <div class="data-grid overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-slate-50">
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $refLabel }}</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $depositedLabel }}</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $notesLabel }}</th>
                        <th class="px-4 py-2.5 w-[48px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($project->collections as $c)
                        @include('projects.external._inflow_row', ['c' => $c, 'showActions' => true])
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                    <tr>
                        <td colspan="3" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $totalLabel }}</td>
                        <td class="px-4 py-2.5 text-right text-[13px] font-bold tabular-nums text-emerald-700">₱{{ number_format($totalCollected, 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif

    </x-project-shell>
</x-app-layout>
