@php
    $totalExpenses = $project->totalExpenses();
@endphp

<x-app-layout :page-title="$project->name">
    <x-project-shell :project="$project" :bank-accounts="$bankAccounts" :collections-chrono="$collectionsChrono">

        @if ($project->expenses->isEmpty())
            <div class="px-4 py-10 text-center">
                <p class="text-sm text-gray-500">No outflows yet. Use the red <strong>Outflow</strong> button above to record an expense paid from a project account.</p>
            </div>
        @else
        <div class="data-grid overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-slate-50">
                        <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Purpose</th>
                        <th class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Source</th>
                        <th class="w-[48px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @php $lastDate = null; @endphp
                    @foreach ($project->expenses->sortBy('spent_on') as $e)
                        @php
                            $purpose = $e->description ?: ($e->vendor_ref ?: '—');
                            // Match the Excel: only show date on the first row of each date group.
                            $showDate = $lastDate !== $e->spent_on->toDateString();
                            $lastDate = $e->spent_on->toDateString();
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/70 {{ $e->isFromTransfer() ? 'bg-rose-50/20' : '' }}">
                            <td class="tabular-nums text-slate-600 whitespace-nowrap">
                                {{ $showDate ? $e->spent_on->format('n/j/y') : '' }}
                            </td>
                            <td>
                                <span class="font-medium text-slate-700">{{ $purpose }}</span>
                                @if ($e->isFromTransfer())
                                    <a href="{{ route('transfers.index') }}"
                                       class="ml-1.5 inline-flex items-center gap-0.5 rounded-full bg-rose-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-rose-800 hover:bg-rose-200"
                                       title="Auto-created by a transfer">
                                        <i data-lucide="arrow-left-right" class="h-2.5 w-2.5"></i> transfer
                                    </a>
                                @endif
                                @if ($e->category)
                                    <span class="ml-1.5 rounded-full bg-slate-100 px-1.5 py-0.5 text-[9.5px] font-medium uppercase tracking-wide text-slate-500">{{ $e->category }}</span>
                                @endif
                                @if ($e->vendor_ref && $e->description && $e->vendor_ref !== $e->description)
                                    <p class="mt-0.5 text-slate-400">{{ $e->vendor_ref }}</p>
                                @endif
                            </td>
                            <td class="text-right font-semibold tabular-nums text-red-600">₱{{ number_format($e->amount, 2) }}</td>
                            <td class="uppercase text-slate-700">
                                {{ $e->bankAccount?->name ?? '—' }}
                                @if ($e->isFromTransfer() && $e->transfer?->toAccount)
                                    <span class="block text-[10px] normal-case text-slate-400">→ {{ $e->transfer->toAccount->name }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($e->isFromTransfer())
                                    <span class="inline-flex rounded p-1 text-slate-300" title="Reverse from the Transfers page">
                                        <i data-lucide="lock" class="h-3 w-3"></i>
                                    </span>
                                @else
                                    <form method="POST" action="{{ route('projects.expenses.destroy', $e) }}" onsubmit="return confirm('Remove this outflow?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded p-1 text-slate-300 transition hover:bg-red-50 hover:text-red-500">
                                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td></td>
                        <td class="text-[11px] font-bold uppercase tracking-wide text-slate-700">SUM</td>
                        <td class="text-right font-bold tabular-nums text-red-600">₱{{ number_format($totalExpenses, 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif

    </x-project-shell>
</x-app-layout>
