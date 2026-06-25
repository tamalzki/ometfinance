@php
    $totalCollected   = $project->totalCollected();
    $completionPct    = $project->contract_value > 0
        ? min(100, round($totalCollected / $project->contract_value * 100, 1))
        : 0;
    $allocLines       = $project->allocationLines;
    $editableLines    = $allocLines->where('row_kind', '!=', \App\Models\ProjectAllocationLine::KIND_BLANK);
    $bucketLines      = $allocLines->where('row_kind', \App\Models\ProjectAllocationLine::KIND_ALLOCATION);
    $bucketPercentSum = (float) $bucketLines->sum('percent');
    $bucketTotalAmt   = $totalCollected * $bucketPercentSum;
    $colCount         = 2 + 2 + $collectionsChrono->count();

    // Actual spend to date — sourced from vouchers via ProjectExpense, but
    // not yet broken down per allocation bucket (no category↔bucket mapping
    // exists yet), so we only surface the project-level total here.
    $runningCost      = $project->totalExpenses();
@endphp

<x-app-layout :page-title="$project->name">
    <x-project-shell :project="$project" :bank-accounts="$bankAccounts" :collections-chrono="$collectionsChrono" :other-projects="$otherProjects">

        <div class="px-2 pt-1 pb-3 sm:px-3" x-data="{ showAdjust: false, showHistory: false }">
            @if ($allocLines->isEmpty())
                <p class="px-2 py-4 text-sm text-gray-500">No allocation template yet.</p>
            @else

            {{-- Adjust action --}}
            <div class="mb-2 flex items-center justify-between gap-3 px-1">
                <p class="min-w-0 text-[11px] leading-snug text-slate-500">
                    <i data-lucide="info" class="mr-1 inline h-3 w-3 align-text-bottom text-slate-400"></i>
                    Allocation applies project-wide. Use
                    <span class="inline-flex items-center gap-0.5 rounded border border-slate-200 bg-white px-1.5 py-px text-[10px] font-medium text-slate-600 shadow-sm ring-1 ring-slate-900/5">
                        <i data-lucide="sliders-horizontal" class="h-2.5 w-2.5 text-omet-blue"></i> Adjust
                    </span>
                    on any column — not just the latest collection.
                </p>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" @click="showHistory = true"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-md ring-1 ring-slate-900/5 transition hover:border-omet-blue hover:text-omet-blue hover:shadow-lg">
                        <i data-lucide="history" class="h-3.5 w-3.5"></i> History
                    </button>
                    @can('manage-financials')
                    <button type="button" @click="showAdjust = true"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-md ring-1 ring-slate-900/5 transition hover:border-omet-blue hover:text-omet-blue hover:shadow-lg">
                        <i data-lucide="sliders-horizontal" class="h-3.5 w-3.5"></i> Adjust allocation
                    </button>
                    @endcan
                </div>
            </div>

            <div class="data-grid overflow-x-auto">
                <table class="w-max min-w-full">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="sticky left-0 z-20 w-[10rem] min-w-[10rem] bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Category</th>
                            <th class="sticky left-[10rem] z-20 w-[4.5rem] min-w-[4.5rem] border-r border-slate-200 bg-slate-50 px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">%</th>
                            <th class="sticky left-[14.5rem] z-20 w-[7rem] min-w-[7rem] bg-slate-50 px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                <div class="flex items-center justify-end gap-0.5">
                                    Allocated
                                    @can('manage-financials')
                                    <button type="button" @click="showAdjust = true" title="Adjust allocation"
                                        class="inline-flex cursor-pointer items-center justify-center rounded-md border border-slate-200 bg-white p-1 text-slate-500 shadow-sm ring-1 ring-slate-900/5 transition hover:border-omet-blue hover:bg-blue-50 hover:text-omet-blue hover:shadow">
                                        <i data-lucide="sliders-horizontal" class="h-3 w-3"></i>
                                    </button>
                                    @endcan
                                </div>
                                <div class="mt-0.5 text-[11px] font-bold normal-case tabular-nums text-omet-navy">₱{{ number_format($totalCollected, 2) }}</div>
                            </th>
                            <th class="sticky left-[21.5rem] z-20 w-[7.5rem] min-w-[7.5rem] border-r border-slate-200 bg-rose-50/40 px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-rose-700">
                                <div class="inline-flex items-center gap-1">
                                    Running cost
                                    
                                    <i data-lucide="info" class="h-3 w-3 text-rose-500" title="No category tagging yet — every voucher's amount is lumped here as one general total until vouchers can be mapped to a bucket."></i>
                                </div>
                                <div class="mt-0.5 text-[11px] font-bold normal-case tabular-nums text-rose-600">₱{{ number_format($runningCost, 2) }}</div>
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
                                <div class="flex items-center justify-end gap-1">
                                    {{ $n . $suffix }} Collection
                                    @can('manage-financials')
                                    <button type="button" @click="showAdjust = true" title="Adjust allocation — applies to every collection"
                                        class="inline-flex cursor-pointer items-center gap-0.5 rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-medium normal-case text-slate-600 shadow-sm ring-1 ring-slate-900/5 transition hover:border-omet-blue hover:bg-blue-50 hover:text-omet-blue hover:shadow">
                                        <i data-lucide="sliders-horizontal" class="h-3 w-3"></i> Adjust
                                    </button>
                                    @endcan
                                </div>
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
                                <td class="sticky left-[14.5rem] z-10 w-[7rem] min-w-[7rem] px-3 py-2.5 text-right tabular-nums text-[13px] font-semibold {{ $isKpi ? 'bg-amber-50/30 text-amber-700' : 'bg-white text-omet-navy' }}">
                                    ₱{{ number_format($totalCollected * $p, 2) }}
                                </td>
                                <td class="sticky left-[21.5rem] z-10 w-[7.5rem] min-w-[7.5rem] border-r border-slate-200 px-3 py-2.5 text-right text-[12px] text-slate-300 {{ $isKpi ? 'bg-amber-50/30' : 'bg-white' }}" title="Not tagged to a bucket yet — see the general total under Running cost.">
                                    —
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
                            <td class="sticky left-[14.5rem] z-10 w-[7rem] min-w-[7rem] bg-slate-50 px-3 py-2.5 text-right tabular-nums text-[13px] font-bold text-slate-800">₱{{ number_format($bucketTotalAmt, 2) }}</td>
                            <td class="sticky left-[21.5rem] z-10 w-[7.5rem] min-w-[7.5rem] border-r border-slate-200 bg-rose-50/40 px-3 py-2.5 text-right tabular-nums text-[13px] font-bold text-rose-600">₱{{ number_format($runningCost, 2) }}</td>
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
                            <td class="sticky left-[14.5rem] z-10 w-[7rem] min-w-[7rem] bg-slate-50 px-3 py-2.5 text-right tabular-nums text-[11px] text-slate-600"
                                @if ($project->contract_value > 0) title="of ₱{{ number_format((float) $project->contract_value, 2) }} contract" @endif>
                                @if ($project->contract_value > 0)
                                ₱{{ number_format($totalCollected, 2) }}
                                @else
                                <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="sticky left-[21.5rem] z-10 w-[7.5rem] min-w-[7.5rem] border-r border-slate-200 bg-slate-50 px-3 py-2.5"></td>
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

            {{-- ════════════════════════════════════════════════════════
                 ADJUST ALLOCATION MODAL
                 Lets the bucket distribution shift as the project status
                 changes — e.g. more weight to Direct Costs mid-execution,
                 less to SOP once mobilization is done.
            ════════════════════════════════════════════════════════ --}}
            @can('manage-financials')
            <div x-show="showAdjust" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-3"
                 @keydown.escape.window="showAdjust = false">
                <div @click.outside="showAdjust = false"
                     class="flex w-full max-w-lg max-h-[calc(100vh-1.5rem)] flex-col overflow-hidden rounded-xl bg-white shadow-xl">
                    <div class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-100 px-5 py-3">
                        <div>
                            <h3 class="text-sm font-semibold text-omet-navy">Adjust allocation</h3>
                            <p class="mt-0.5 text-[11px] leading-snug text-slate-500">Recalculates existing collections when saved.</p>
                        </div>
                        <button @click="showAdjust = false" class="shrink-0 rounded p-1 text-gray-400 hover:text-gray-600" aria-label="Close">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('projects.allocation.update', $project) }}"
                        class="flex min-h-0 flex-1 flex-col overflow-y-auto px-5 py-3"
                        x-data="{
                            base: {{ $totalCollected }},
                            percents: { @foreach ($editableLines as $line) {{ $line->id }}: {{ number_format($line->percent * 100, 2, '.', '') }}, @endforeach },
                            amounts: { @foreach ($editableLines as $line) {{ $line->id }}: {{ number_format($line->percent * $totalCollected, 2, '.', '') }}, @endforeach },
                            syncFromPercent(id) {
                                const p = parseFloat(this.percents[id]) || 0;
                                this.amounts[id] = this.base > 0 ? (this.base * p / 100).toFixed(2) : '0.00';
                            },
                            syncFromAmount(id) {
                                const a = parseFloat(this.amounts[id]) || 0;
                                this.percents[id] = this.base > 0 ? (a / this.base * 100).toFixed(2) : '0.00';
                            },
                            get bucketTotal() {
                                return Object.entries(this.percents).reduce((sum, [id, val]) => {
                                    return {{ \Illuminate\Support\Js::from($bucketLines->pluck('id')) }}.includes(parseInt(id)) ? sum + (parseFloat(val) || 0) : sum;
                                }, 0);
                            },
                            get bucketAmount() {
                                return this.base > 0 ? this.base * this.bucketTotal / 100 : 0;
                            },
                        }">
                        @csrf
                        @method('PUT')

                        <p class="mb-3 flex items-start gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-[10px] leading-snug text-amber-800">
                            <i data-lucide="alert-triangle" class="mt-0.5 h-3 w-3 shrink-0 text-amber-500"></i>
                            This changes the percentages for the whole project — every collection's allocated amount above is recalculated immediately, not just the latest one.
                        </p>

                        @if ($totalCollected > 0)
                        <div class="mb-4 flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5">
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Total collected</span>
                            <span class="text-xs font-bold tabular-nums text-omet-navy">₱{{ number_format($totalCollected, 2) }}</span>
                        </div>
                        @else
                        <p class="mb-4 flex items-start gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-[10px] leading-snug text-slate-500">
                            <i data-lucide="info" class="mt-0.5 h-3 w-3 shrink-0 text-slate-400"></i>
                            No collections yet — adjust by percent for now.
                        </p>
                        @endif

                        <div class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                            <table class="w-full table-fixed">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-50/90">
                                        <th class="px-2.5 py-1.5 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-400">Category</th>
                                        <th class="w-[7.5rem] px-2 py-1.5 text-right text-[10px] font-semibold uppercase tracking-wide text-slate-400">Amount</th>
                                        <th class="w-[5rem] px-2 py-1.5 text-right text-[10px] font-semibold uppercase tracking-wide text-slate-400">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($editableLines as $line)
                                    @php $isKpi = $line->row_kind === \App\Models\ProjectAllocationLine::KIND_KPI; @endphp
                                    <tr @class(['bg-amber-50/40' => $isKpi])>
                                        <td class="px-2.5 py-1">
                                            <span class="text-[12px] font-medium leading-tight {{ $isKpi ? 'text-amber-900' : 'text-slate-700' }}">
                                                {{ $line->label ?: '—' }}
                                            </span>
                                            @if ($isKpi)
                                            <span class="ml-1 inline-flex rounded bg-amber-100 px-1 py-px text-[8px] font-semibold uppercase text-amber-700">KPI</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1">
                                            <div class="flex h-7 w-full items-stretch rounded-md border border-slate-200 bg-white focus-within:border-omet-blue focus-within:ring-1 focus-within:ring-omet-blue/20">
                                                <span class="flex w-7 shrink-0 items-center justify-center bg-slate-50 text-[11px] text-slate-400 [box-shadow:inset_-1px_0_0_0_rgb(226_232_240)]">₱</span>
                                                <input type="number"
                                                    x-model.number="amounts[{{ $line->id }}]"
                                                    @input="syncFromAmount({{ $line->id }})"
                                                    :disabled="base <= 0"
                                                    class="min-w-0 flex-1 border-0 bg-transparent px-2 text-right text-[12px] tabular-nums outline-none focus:ring-0 disabled:bg-slate-50 disabled:text-slate-400"
                                                    min="0" step="0.01" placeholder="0.00">
                                            </div>
                                        </td>
                                        <td class="px-2 py-1">
                                            <div class="flex h-7 w-full items-stretch rounded-md border border-slate-200 bg-white focus-within:border-omet-blue focus-within:ring-1 focus-within:ring-omet-blue/20">
                                                <input type="number"
                                                    name="percents[{{ $line->id }}]"
                                                    x-model="percents[{{ $line->id }}]"
                                                    @input="syncFromPercent({{ $line->id }})"
                                                    class="min-w-0 flex-1 border-0 bg-transparent px-2 text-right text-[12px] tabular-nums outline-none focus:ring-0"
                                                    min="0" max="100" step="0.01" required>
                                                <span class="flex w-7 shrink-0 items-center justify-center bg-slate-50 text-[11px] text-slate-400 [box-shadow:inset_1px_0_0_0_rgb(226_232_240)]">%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex items-center justify-between rounded-md border border-indigo-100 bg-indigo-50/50 px-3 py-1.5">
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Bucket subtotal</span>
                            <div class="flex items-center gap-2.5 text-xs font-bold tabular-nums text-indigo-700">
                                <span x-text="'₱' + bucketAmount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                                <span class="font-normal text-indigo-300">·</span>
                                <span x-text="bucketTotal.toFixed(2) + '%'"></span>
                            </div>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between rounded-md border px-3 py-1.5"
                             :class="(100 - bucketTotal) < -0.01 ? 'border-rose-200 bg-rose-50' : 'border-emerald-100 bg-emerald-50/50'">
                            <span class="text-[10px] font-semibold uppercase tracking-wide"
                                  :class="(100 - bucketTotal) < -0.01 ? 'text-rose-700' : 'text-slate-500'"
                                  x-text="(100 - bucketTotal) < -0.01 ? 'Over-allocated by' : 'Unallocated remaining'"></span>
                            <div class="flex items-center gap-2.5 text-xs font-bold tabular-nums"
                                 :class="(100 - bucketTotal) < -0.01 ? 'text-rose-700' : 'text-emerald-700'">
                                <span x-text="'₱' + Math.abs(base * (100 - bucketTotal) / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                                <span class="font-normal opacity-50">·</span>
                                <span x-text="Math.abs(100 - bucketTotal).toFixed(2) + '%'"></span>
                            </div>
                        </div>
                        <p class="mt-1 text-[10px] leading-snug text-slate-400">
                            KPI rows are derived. Bucket rows can't add up to more than 100% — that would allocate more than the ₱{{ number_format($totalCollected, 2) }} actually collected.
                        </p>

                        <div class="mt-3 flex shrink-0 justify-end gap-2 border-t border-gray-100 pt-3">
                            <button type="button" @click="showAdjust = false"
                                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                            <button type="submit"
                                :disabled="(100 - bucketTotal) < -0.01"
                                :class="(100 - bucketTotal) < -0.01 ? 'cursor-not-allowed bg-slate-300' : 'bg-omet-blue hover:bg-omet-lightblue'"
                                class="rounded-lg px-4 py-1.5 text-xs font-semibold text-white shadow-sm">Save allocation</button>
                        </div>
                    </form>
                </div>
            </div>
            @endcan

            {{-- ════════════════════════════════════════════════════════
                 ALLOCATION HISTORY
                 Simple read-only log of percent changes — reuses the audit
                 trail already written whenever a line is updated.
            ════════════════════════════════════════════════════════ --}}
            <div x-show="showHistory" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-3"
                 @keydown.escape.window="showHistory = false">
                <div @click.outside="showHistory = false"
                     class="flex w-full max-w-md max-h-[calc(100vh-1.5rem)] flex-col overflow-hidden rounded-xl bg-white shadow-xl">
                    <div class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-100 px-5 py-3">
                        <div>
                            <h3 class="text-sm font-semibold text-omet-navy">Allocation history</h3>
                            <p class="mt-0.5 text-[11px] leading-snug text-slate-500">Every percent change, most recent first.</p>
                        </div>
                        <button @click="showHistory = false" class="shrink-0 rounded p-1 text-gray-400 hover:text-gray-600" aria-label="Close">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-3">
                        @if ($allocationHistory->isEmpty())
                        <p class="py-6 text-center text-[12px] text-slate-400">No adjustments yet — this project still uses its starting allocation.</p>
                        @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($allocationHistory as $entry)
                            @php
                                $label = $allocLines->firstWhere('id', $entry->auditable_id)?->label ?: '—';
                                $from  = (float) ($entry->old_values['percent'] ?? 0) * 100;
                                $to    = (float) ($entry->new_values['percent'] ?? 0) * 100;
                            @endphp
                            <li class="py-2 text-[12px]">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-slate-700">{{ $label }}</span>
                                    <span class="tabular-nums text-slate-600">
                                        {{ number_format($from, 2) }}% <i data-lucide="arrow-right" class="inline h-3 w-3 text-slate-400"></i> <span class="font-semibold text-indigo-700">{{ number_format($to, 2) }}%</span>
                                    </span>
                                </div>
                                <p class="mt-0.5 text-[10.5px] text-slate-400">
                                    {{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->format('M j, Y g:i A') }}
                                </p>
                            </li>
                            @endforeach
                        </ul>
                        @endif
                    </div>
                </div>
            </div>

            @endif
        </div>

    </x-project-shell>
</x-app-layout>
