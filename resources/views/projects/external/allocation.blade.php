@php
    $totalCollected   = $project->totalCollected();
    $completionPct    = $project->contract_value > 0
        ? min(100, round($totalCollected / $project->contract_value * 100, 1))
        : 0;
    $allocLines       = $project->allocationLines;
    $bucketLines      = $allocLines->where('row_kind', \App\Models\ProjectAllocationLine::KIND_ALLOCATION);
    $bucketPercentSum = (float) $bucketLines->sum('percent');
    $bucketTotalAmt   = $totalCollected * $bucketPercentSum;
    $colCount         = 2 + 1 + $collectionsChrono->count();
@endphp

<x-app-layout :page-title="$project->name">
    <x-project-shell :project="$project" :bank-accounts="$bankAccounts" :collections-chrono="$collectionsChrono">

        <div class="px-2 pt-3 pb-3 sm:px-3">
            @if ($allocLines->isEmpty())
                <p class="px-2 py-4 text-sm text-gray-500">No allocation template yet.</p>
            @else
            <div class="data-grid overflow-x-auto">
                <table class="w-max min-w-full">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="sticky left-0 z-20 w-[10rem] min-w-[10rem] bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Category</th>
                            <th class="sticky left-[10rem] z-20 w-[4.5rem] min-w-[4.5rem] border-r border-slate-200 bg-slate-50 px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">%</th>
                            <th class="min-w-[9rem] px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                <div>Total collected</div>
                                <div class="mt-0.5 text-[11px] font-bold normal-case tabular-nums text-omet-navy">₱{{ number_format($totalCollected, 2) }}</div>
                            </th>
                            @foreach ($collectionsChrono as $idx => $coll)
                            @php
                                $n = $idx + 1;
                                $suffix = match (true) {
                                    $n % 100 >= 11 && $n % 100 <= 13 => 'th',
                                    $n % 10 === 1 => 'st',
                                    $n % 10 === 2 => 'nd',
                                    $n % 10 === 3 => 'rd',
                                    default => 'th',
                                };
                            @endphp
                            <th class="min-w-[9rem] px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                <div>{{ $n . $suffix }} Collection</div>
                                <div class="mt-0.5 text-[10.5px] font-normal normal-case text-slate-400">{{ $coll->collected_on->format('M j, Y') }}</div>
                                <div class="mt-0.5 text-[11.5px] font-bold normal-case tabular-nums text-omet-navy">₱{{ number_format($coll->amount, 2) }}</div>
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($allocLines as $line)
                            @if ($line->row_kind === \App\Models\ProjectAllocationLine::KIND_BLANK)
                            <tr><td colspan="{{ $colCount }}" class="py-1.5 bg-slate-50/50"></td></tr>
                            @else
                            @php
                                $p     = (float) $line->percent;
                                $isKpi = $line->row_kind === \App\Models\ProjectAllocationLine::KIND_KPI;
                            @endphp
                            <tr class="transition-colors hover:bg-slate-50/50 {{ $isKpi ? 'bg-amber-50/30' : '' }}">
                                <td class="sticky left-0 z-10 w-[10rem] min-w-[10rem] px-4 py-2.5 text-[13px] font-semibold {{ $isKpi ? 'bg-amber-50/30 text-amber-900' : 'bg-white text-slate-800' }}">
                                    {{ $line->label ?: '—' }}
                                </td>
                                <td class="sticky left-[10rem] z-10 w-[4.5rem] min-w-[4.5rem] border-r border-slate-200 px-3 py-2.5 text-right tabular-nums text-[13px] {{ $isKpi ? 'bg-amber-50/30 font-semibold text-amber-700' : 'bg-white text-slate-600' }}">
                                    {{ number_format($p * 100, 2) }}%
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-[13px] font-semibold text-omet-navy">
                                    ₱{{ number_format($totalCollected * $p, 2) }}
                                </td>
                                @foreach ($collectionsChrono as $coll)
                                <td class="px-4 py-2.5 text-right tabular-nums text-[13px] {{ $isKpi ? 'font-semibold text-amber-700' : 'text-slate-700' }}">
                                    ₱{{ number_format((float) $coll->amount * $p, 2) }}
                                </td>
                                @endforeach
                            </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                        <tr>
                            <td class="sticky left-0 z-10 w-[10rem] min-w-[10rem] bg-slate-50 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Subtotal</td>
                            <td class="sticky left-[10rem] z-10 w-[4.5rem] min-w-[4.5rem] border-r border-slate-200 bg-slate-50 px-3 py-2.5 text-right tabular-nums text-[12px] font-semibold text-slate-700">{{ number_format($bucketPercentSum * 100, 2) }}%</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-[13px] font-bold text-slate-800">₱{{ number_format($bucketTotalAmt, 2) }}</td>
                            @foreach ($collectionsChrono as $coll)
                            @php $collBucket = (float) $coll->amount * $bucketPercentSum; @endphp
                            <td class="px-4 py-2.5 text-right tabular-nums text-[13px] font-bold text-slate-800">₱{{ number_format($collBucket, 2) }}</td>
                            @endforeach
                        </tr>
                        <tr class="border-t border-slate-200">
                            <td class="sticky left-0 z-10 w-[10rem] min-w-[10rem] bg-slate-50 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Collection rate</td>
                            <td class="sticky left-[10rem] z-10 w-[4.5rem] min-w-[4.5rem] border-r border-slate-200 bg-slate-50 px-3 py-2.5 text-right tabular-nums text-[12px] font-bold text-indigo-600">
                                {{ $project->contract_value > 0 ? $completionPct . '%' : '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-[12px] text-slate-600">
                                @if ($project->contract_value > 0)
                                ₱{{ number_format($totalCollected, 2) }} / ₱{{ number_format((float) $project->contract_value, 2) }}
                                @else
                                <span class="text-slate-400">No contract value set</span>
                                @endif
                            </td>
                            @foreach ($collectionsChrono as $coll)
                            <td class="px-4 py-2.5"></td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if ($collectionsChrono->isEmpty())
            <p class="mt-2 px-2 text-[11px] text-gray-500">Click the green <strong class="text-emerald-700">Inflow</strong> button above to add your first collection — it appears as a new column here, just like the Excel.</p>
            @endif
            @endif
        </div>

    </x-project-shell>
</x-app-layout>
