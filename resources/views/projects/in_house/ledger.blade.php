@php
    // Build a unified chronological ledger of inflows + outflows.
    $entries = $project->collections->map(fn ($c) => (object) [
        'date'        => $c->collected_on,
        'type'        => 'in',
        'reference'   => $c->reference,
        'description' => $c->notes,
        'account'     => $c->bankAccount?->name,
        'category'    => null,
        'amount'      => (float) $c->amount,
        'is_transfer' => $c->isFromTransfer(),
        'sort_key'    => optional($c->collected_on)->format('Y-m-d') . '-1-' . str_pad((string) $c->id, 8, '0', STR_PAD_LEFT),
    ])->concat(
        $project->expenses->map(fn ($e) => (object) [
            'date'        => $e->spent_on,
            'type'        => 'out',
            'reference'   => $e->vendor_ref,
            'description' => $e->description,
            'account'     => $e->bankAccount?->name,
            'category'    => $e->category,
            'amount'      => (float) $e->amount,
            'is_transfer' => $e->isFromTransfer(),
            'sort_key'    => optional($e->spent_on)->format('Y-m-d') . '-2-' . str_pad((string) $e->id, 8, '0', STR_PAD_LEFT),
        ])
    )->sortBy('sort_key')->values();

    $totalIn   = $project->totalCollected();
    $totalOut  = $project->totalExpenses();
    $netCash   = $totalIn - $totalOut;
@endphp

<x-app-layout :page-title="$project->name">
    <x-project-shell :project="$project" :bank-accounts="$bankAccounts" :collections-chrono="$collectionsChrono">

        <div class="flex items-center justify-between gap-4 border-b border-gray-100 bg-slate-50/60 px-4 py-2">
            <div class="flex items-baseline gap-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-omet-navy">Project ledger</h3>
                <span class="text-[11px] text-gray-500">
                    {{ $entries->count() }} {{ $entries->count() === 1 ? 'entry' : 'entries' }} · chronological
                </span>
            </div>
            <div class="flex items-center gap-4 text-[11px] tabular-nums">
                <span class="text-emerald-700"><span class="font-semibold">Funded:</span> ₱{{ number_format($totalIn, 2) }}</span>
                <span class="text-red-600"><span class="font-semibold">Spent:</span> ₱{{ number_format($totalOut, 2) }}</span>
                <span class="{{ $netCash >= 0 ? 'text-omet-navy' : 'text-red-600' }}"><span class="font-semibold">Balance:</span> ₱{{ number_format($netCash, 2) }}</span>
            </div>
        </div>

        @if ($entries->isEmpty())
            <div class="px-4 py-10 text-center">
                <p class="text-sm text-gray-500">No entries yet. Use the <strong class="text-emerald-700">Funding</strong> or <strong class="text-red-600">Outflow</strong> buttons above to start the ledger.</p>
            </div>
        @else
        <div class="data-grid overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-slate-50">
                        <th class="px-4 py-2.5 text-left text-[10.5px] font-semibold uppercase tracking-wider text-slate-500 w-[50px]">#</th>
                        <th class="px-4 py-2.5 text-left text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Date</th>
                        <th class="px-4 py-2.5 text-left text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Reference / Description</th>
                        <th class="px-4 py-2.5 text-left text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Account</th>
                        <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Funding</th>
                        <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Outflow</th>
                        <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php $balance = 0; @endphp
                    @foreach ($entries as $idx => $row)
                        @php
                            $balance += $row->type === 'in' ? $row->amount : -$row->amount;
                            $primary = $row->reference ?: ($row->description ?: '—');
                            $secondary = $row->reference && $row->description ? $row->description : null;
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/70 {{ $row->type === 'in' ? 'bg-emerald-50/10' : '' }}">
                            <td class="px-4 py-2.5 text-[12px] font-semibold text-slate-400 tabular-nums">{{ $idx + 1 }}</td>
                            <td class="px-4 py-2.5 tabular-nums text-[13px] text-slate-600 whitespace-nowrap">
                                {{ $row->date ? $row->date->format('M j, Y') : '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[13px] font-medium text-slate-700">{{ $primary }}</span>
                                    @if ($row->is_transfer)
                                        <span class="inline-flex items-center gap-0.5 rounded-full {{ $row->type === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }} px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider">
                                            <i data-lucide="arrow-left-right" class="h-2.5 w-2.5"></i> transfer
                                        </span>
                                    @endif
                                    @if ($row->category)
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium capitalize text-slate-600">{{ $row->category }}</span>
                                    @endif
                                </div>
                                @if ($secondary)
                                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $secondary }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-[12px] text-slate-500">{{ $row->account ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-[13px] font-semibold text-emerald-700">
                                {{ $row->type === 'in' ? '₱' . number_format($row->amount, 2) : '' }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-[13px] font-semibold text-red-600">
                                {{ $row->type === 'out' ? '₱' . number_format($row->amount, 2) : '' }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-[13px] font-bold {{ $balance >= 0 ? 'text-omet-navy' : 'text-red-600' }}">
                                ₱{{ number_format($balance, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                    <tr>
                        <td colspan="4" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Totals</td>
                        <td class="px-4 py-2.5 text-right text-[13px] font-bold tabular-nums text-emerald-700">₱{{ number_format($totalIn, 2) }}</td>
                        <td class="px-4 py-2.5 text-right text-[13px] font-bold tabular-nums text-red-600">₱{{ number_format($totalOut, 2) }}</td>
                        <td class="px-4 py-2.5 text-right text-[13px] font-bold tabular-nums {{ $netCash >= 0 ? 'text-omet-navy' : 'text-red-600' }}">
                            ₱{{ number_format($netCash, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif

    </x-project-shell>
</x-app-layout>
