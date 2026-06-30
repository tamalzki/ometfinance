<div id="disburse-list-fragment" class="disburse-data-grid transition-opacity" data-result-count="{{ $transfers->total() }}" data-result-mode="{{ $search ? 'matching' : 'shown' }}">
    <table class="min-w-full">
        <thead class="sticky top-0 z-20">
            <tr>
                <th scope="col" class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[104px]">Date</th>
                <th scope="col" class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">From</th>
                <th scope="col" class="text-center text-[11px] font-semibold text-slate-400 w-[2.25rem]">→</th>
                <th scope="col" class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">To</th>
                <th scope="col" class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[130px]">Purpose</th>
                <th scope="col" class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Projects</th>
                <th scope="col" class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Reason / Memo</th>
                <th scope="col" class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[124px]">Amount</th>
                <th scope="col" class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 whitespace-nowrap min-w-[8rem]">Actions</th>
            </tr>
        </thead>
            <tbody>
                @forelse ($transfers as $t)
                    @php
                        $isIntercompany = $t->isIntercompany();
                    @endphp
                    <tr class="group transition-colors hover:bg-slate-50/70">
                        <td class="border-b border-slate-100 px-4 py-2.5 tabular-nums text-[12.5px] text-slate-600 whitespace-nowrap align-top">
                            {{ $t->date->format('M d, Y') }}
                        </td>
                        <td class="border-b border-slate-100 px-4 py-2.5 align-top">
                            @if ($t->fromAccount)
                                <span class="block text-[13px] font-medium text-slate-700">{{ $t->fromAccount->name }}</span>
                                @if ($t->fromAccount->entity?->name)
                                    <span class="block text-[11.5px] text-slate-400">{{ $t->fromAccount->entity->name }}</span>
                                @endif
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="border-b border-slate-100 px-2 py-2.5 align-top text-center text-slate-300" aria-hidden="true">→</td>
                        <td class="border-b border-slate-100 px-4 py-2.5 align-top">
                            @if ($t->toAccount)
                                <span class="block text-[13px] font-medium text-slate-700">{{ $t->toAccount->name }}</span>
                                @if ($t->toAccount->entity?->name)
                                    <span class="block text-[11.5px] text-slate-400">{{ $t->toAccount->entity->name }}</span>
                                @endif
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="border-b border-slate-100 px-4 py-2.5 align-top">
                            <span class="block text-[13px] text-slate-700">{{ $t->purposeLabel() }}</span>
                            @if ($isIntercompany)
                                <span class="text-[11px] text-slate-400">intercompany</span>
                            @endif
                        </td>
                        <td class="border-b border-slate-100 px-4 py-2.5 align-top">
                            @if ($t->fromProject || $t->toProject)
                                <div class="flex flex-col gap-0.5">
                                    @if ($t->fromProject)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-1.5 py-0.5 text-[10.5px] font-medium text-rose-700 ring-1 ring-rose-100">
                                            <i data-lucide="trending-down" class="h-3 w-3"></i>
                                            Out: <a href="{{ route('projects.show', $t->fromProject) }}" class="font-semibold underline-offset-2 hover:underline">{{ $t->fromProject->name }}</a>
                                        </span>
                                    @endif
                                    @if ($t->toProject)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10.5px] font-medium text-emerald-700 ring-1 ring-emerald-100">
                                            <i data-lucide="trending-up" class="h-3 w-3"></i>
                                            In: <a href="{{ route('projects.show', $t->toProject) }}" class="font-semibold underline-offset-2 hover:underline">{{ $t->toProject->name }}</a>
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="border-b border-slate-100 px-4 py-2.5 align-top">
                            @if ($t->reason)
                                <span class="block text-[13px] text-slate-700">{{ $t->reason }}</span>
                            @endif
                            @if ($t->memo)
                                <span class="block text-[11.5px] text-slate-400">{{ $t->memo }}</span>
                            @endif
                            @if (!$t->reason && !$t->memo)
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="border-b border-slate-100 px-4 py-2.5 align-top text-right text-[13px] font-semibold tabular-nums text-omet-navy whitespace-nowrap">
                            ₱{{ number_format($t->amount, 2) }}
                        </td>
                        <td class="border-b border-slate-100 px-3 py-2.5 align-middle">
                            <div class="flex flex-row flex-nowrap items-center justify-end gap-1.5">
                                @php
                                    $editPayload = [
                                        'id' => $t->id,
                                        'from_account_id' => $t->from_account_id,
                                        'to_account_id' => $t->to_account_id,
                                        'from_project_id' => $t->from_project_id,
                                        'to_project_id' => $t->to_project_id,
                                        'date' => $t->date->format('Y-m-d'),
                                        'amount' => $t->amount,
                                        'purpose' => $t->purpose,
                                        'memo' => $t->memo,
                                        'reason' => $t->reason,
                                    ];
                                @endphp
                                <button type="button"
                                        @click="openEdit({{ \Illuminate\Support\Js::from($editPayload) }})"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50">
                                    <i data-lucide="pencil" class="h-3 w-3 shrink-0 pointer-events-none"></i>
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('transfers.destroy', $t->id) }}"
                                      onsubmit="return confirm('Delete this transfer? Bank ledger and any project rows it created will be removed.');"
                                      class="inline-flex shrink-0">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-600 shadow-sm transition hover:bg-red-100">
                                        <i data-lucide="trash-2" class="h-3 w-3 shrink-0 pointer-events-none"></i>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-14 text-center">
                            <i data-lucide="arrow-left-right" class="mx-auto mb-2 h-8 w-8 text-slate-200"></i>
                            <p class="text-xs text-slate-400">
                                @if ($from || $to)
                                    No transfers match the current filters.
                                @else
                                    No transfers recorded yet. Use <span class="font-semibold text-[#185FA5]">Add Transfer</span>.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <x-pagination-simple :paginator="$transfers" />
</div>
