@php
    $totalExpenses = $project->totalExpenses();
@endphp

<x-app-layout :page-title="$project->name">
    <x-project-shell :project="$project" :bank-accounts="$bankAccounts" :collections-chrono="$collectionsChrono">

        @if ($project->expenses->isEmpty())
            <div class="px-4 py-10 text-center">
                <p class="text-sm text-gray-500">No outflows yet. Use the red <strong>Outflow</strong> button above to record one.</p>
            </div>
        @else
        <div class="data-grid overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-slate-50">
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Description</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Category</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Vendor / ref</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Paid from</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-2.5 w-[48px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($project->expenses as $e)
                        @include('projects.external._outflow_row', ['e' => $e, 'showActions' => true])
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                    <tr>
                        <td colspan="5" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total outflow</td>
                        <td class="px-4 py-2.5 text-right text-[13px] font-bold tabular-nums text-red-600">₱{{ number_format($totalExpenses, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif

    </x-project-shell>
</x-app-layout>
