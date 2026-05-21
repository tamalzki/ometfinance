<x-app-layout :page-title="$project->name">
    <x-project-shell :project="$project" :bank-accounts="$bankAccounts" :collections-chrono="$collectionsChrono">

        <div class="flex items-center justify-between gap-4 border-b border-gray-100 bg-slate-50/60 px-4 py-2">
            <div class="flex items-baseline gap-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-omet-navy">Collection history</h3>
                <span class="text-[11px] text-gray-500">{{ $project->collections->count() }} collection{{ $project->collections->count() === 1 ? '' : 's' }} recorded</span>
            </div>
        </div>

        @if ($project->collections->isEmpty())
            <div class="px-4 py-10 text-center">
                <p class="text-sm text-gray-500">No collections recorded yet.</p>
            </div>
        @else
        <div class="data-grid overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-slate-50">
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[60px]">#</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Reference</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Deposited to</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Running total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php $running = 0; @endphp
                    @foreach ($collectionsChrono as $idx => $coll)
                        @php
                            $running += (float) $coll->amount;
                            $n = $idx + 1;
                            $suffix = match (true) {
                                $n % 100 >= 11 && $n % 100 <= 13 => 'th',
                                $n % 10 === 1 => 'st',
                                $n % 10 === 2 => 'nd',
                                $n % 10 === 3 => 'rd',
                                default => 'th',
                            };
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/70">
                            <td class="px-4 py-2.5 text-[12px] font-semibold text-omet-navy">{{ $n . $suffix }}</td>
                            <td class="px-4 py-2.5 tabular-nums text-[13px] text-slate-600">{{ $coll->collected_on->format('M j, Y') }}</td>
                            <td class="px-4 py-2.5 text-[13px] text-slate-700">{{ $coll->reference ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-[13px] text-slate-700">{{ $coll->bankAccount?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right text-[13px] font-semibold tabular-nums text-emerald-700">₱{{ number_format($coll->amount, 2) }}</td>
                            <td class="px-4 py-2.5 text-right text-[13px] tabular-nums text-slate-700">₱{{ number_format($running, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </x-project-shell>
</x-app-layout>
