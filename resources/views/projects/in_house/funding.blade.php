@php
    $totalFunded = $project->totalCollected();

    $borrowRows  = $project->collections->filter(fn ($c) => $c->isFromTransfer() && ! $c->transfer?->from_project_id);
    $supportRows = $project->collections->filter(fn ($c) => $c->isFromTransfer() && $c->transfer?->from_project_id);
    $manualRows  = $project->collections->filter(fn ($c) => ! $c->isFromTransfer());

    $borrowTotal  = (float) $borrowRows->sum(fn ($c) => (float) $c->amount);
    $supportTotal = (float) $supportRows->sum(fn ($c) => (float) $c->amount);
    $manualTotal  = (float) $manualRows->sum(fn ($c) => (float) $c->amount);
@endphp

<x-app-layout :page-title="$project->name">
    <x-project-shell :project="$project" :bank-accounts="$bankAccounts" :collections-chrono="$collectionsChrono" :other-projects="$otherProjects">

        @if ($project->collections->isEmpty())
            <div class="px-4 py-12 text-center">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50">
                    <i data-lucide="arrow-left-right" class="h-5 w-5 text-indigo-600"></i>
                </div>
                <p class="mt-3 text-sm font-medium text-slate-700">No funding yet</p>
                <p class="mx-auto mt-1 max-w-sm text-[12px] leading-relaxed text-slate-500">
                    In-house projects don't earn income — money comes in by
                    <span class="font-semibold text-indigo-700">borrowing from another account</span> or as
                    <span class="font-semibold text-indigo-700">support from another project</span>.
                    Both move funds between your accounts and keep the ledgers in sync.
                </p>
                @can('manage-financials')
                <button type="button" @click="showNewCollection = true"
                    class="mt-4 inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow ring-1 ring-emerald-700/20 hover:bg-emerald-700">
                    <i data-lucide="plus-circle" class="h-3.5 w-3.5"></i> Record first funding
                </button>
                @endcan
            </div>
        @else
        <div class="space-y-3">

            {{-- Funding mix --}}
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex items-center gap-2 rounded-lg border border-indigo-100 bg-indigo-50/60 px-3 py-1.5">
                    <i data-lucide="landmark" class="h-3.5 w-3.5 text-indigo-600"></i>
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-indigo-800">Borrowed from accounts</span>
                    <span class="text-[13px] font-bold tabular-nums text-indigo-700">₱{{ number_format($borrowTotal, 2) }}</span>
                    <span class="text-[11px] tabular-nums text-indigo-600/70">· {{ $borrowRows->count() }}</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-lg border border-sky-100 bg-sky-50/60 px-3 py-1.5">
                    <i data-lucide="heart-handshake" class="h-3.5 w-3.5 text-sky-600"></i>
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-sky-800">Project support</span>
                    <span class="text-[13px] font-bold tabular-nums text-sky-700">₱{{ number_format($supportTotal, 2) }}</span>
                    <span class="text-[11px] tabular-nums text-sky-600/70">· {{ $supportRows->count() }}</span>
                </div>
                @if ($manualRows->isNotEmpty())
                <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5"
                     title="Entries recorded by hand before funding moved to transfers">
                    <i data-lucide="pencil-line" class="h-3.5 w-3.5 text-slate-500"></i>
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-600">Manual (legacy)</span>
                    <span class="text-[13px] font-bold tabular-nums text-slate-600">₱{{ number_format($manualTotal, 2) }}</span>
                    <span class="text-[11px] tabular-nums text-slate-500/70">· {{ $manualRows->count() }}</span>
                </div>
                @endif
            </div>

            <div class="data-grid overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Source</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Deposited to</th>
                            <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Notes</th>
                            <th class="px-4 py-2.5 w-[48px]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($project->collections as $c)
                        @php
                            $fromAccount  = $c->transfer?->fromAccount;
                            $fromProject  = $c->transfer?->fromProject;
                            $isSupport    = $c->isFromTransfer() && $fromProject;
                        @endphp
                        <tr class="transition-colors hover:bg-slate-50/70">
                            <td class="px-4 py-2.5 tabular-nums text-[13px] text-slate-600">{{ $c->collected_on->format('M j, Y') }}</td>
                            <td class="px-4 py-2.5">
                                @if ($c->isFromTransfer())
                                    <span class="flex items-center gap-1.5 text-[13px] text-slate-700">
                                        <i data-lucide="{{ $isSupport ? 'heart-handshake' : 'landmark' }}" class="h-3.5 w-3.5 {{ $isSupport ? 'text-sky-500' : 'text-indigo-500' }}"></i>
                                        {{ $fromAccount?->name ?? '—' }}
                                    </span>
                                    <span class="ml-5 block text-[11px] text-slate-400">
                                        @if ($isSupport)
                                            support from {{ $fromProject->name }}
                                        @else
                                            borrowed · {{ $fromAccount?->entity?->name ?? 'account transfer' }}
                                        @endif
                                    </span>
                                @else
                                    <span class="text-[13px] text-slate-700">{{ $c->reference ?: 'Manual entry' }}</span>
                                    <span class="block text-[11px] text-slate-400">recorded by hand (legacy)</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-[13px] text-slate-700">{{ $c->bankAccount?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right text-[13px] font-semibold tabular-nums text-emerald-700">₱{{ number_format($c->amount, 2) }}</td>
                            <td class="px-4 py-2.5 text-[12px] text-slate-500">{{ $c->notes ?? '' }}</td>
                            <td class="px-3 py-2.5 text-right">
                                @if ($c->isFromTransfer())
                                    <span class="inline-flex rounded p-1 text-slate-300" title="Reverse from the Transfers page">
                                        <i data-lucide="lock" class="h-3 w-3"></i>
                                    </span>
                                @else
                                    <form method="POST" action="{{ route('projects.collections.destroy', $c) }}" onsubmit="return confirm('Remove this funding entry?')">
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
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                        <tr>
                            <td colspan="3" class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total funded</td>
                            <td class="px-4 py-2.5 text-right text-[13px] font-bold tabular-nums text-emerald-700">₱{{ number_format($totalFunded, 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

    </x-project-shell>
</x-app-layout>
