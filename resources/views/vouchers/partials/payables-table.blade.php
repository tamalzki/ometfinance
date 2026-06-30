<div id="disburse-list-fragment" class="disburse-data-grid transition-opacity" data-result-count="{{ $rows->total() }}" data-result-mode="{{ $activeSearch ? 'matching' : 'open' }}">
    <table class="min-w-full">
        <thead class="sticky top-0 z-20">
            <tr>
                <th class="bg-slate-50 border-b border-slate-200 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[108px]">Voucher</th>
                <th class="bg-slate-50 border-b border-slate-200 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Payee</th>
                <th class="bg-slate-50 border-b border-slate-200 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Project</th>
                <th class="bg-slate-50 border-b border-slate-200 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[116px]">Voucher date</th>
                <th class="bg-slate-50 border-b border-slate-200 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[116px]">Due date</th>
                <th class="bg-slate-50 border-b border-slate-200 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[140px]">Aging</th>
                <th class="bg-slate-50 border-b border-slate-200 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[90px]">Type</th>
                <th class="bg-slate-50 border-b border-slate-200 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[126px]">Payable</th>
                <th class="bg-slate-50 border-b border-slate-200 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[126px]">Balance due</th>
                <th class="sticky right-0 z-30 bg-slate-50 border-b border-slate-200 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[110px]">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $v)
                @php
                    $balance  = $v->balanceDue();
                    $days     = $v->daysUntilDue();
                    $bucket   = $v->agingBucket();
                    $cfg      = $bucketConfig[$bucket] ?? ['label' => '—', 'badge' => 'bg-slate-100 text-slate-600 ring-slate-200', 'text' => 'text-slate-600'];
                    $overdue  = in_array($bucket, ['d1_30', 'd31_60', 'd60_plus'], true);
                    $payload  = ['id' => $v->id, 'no' => $v->voucher_no, 'payee' => $v->payee_name,
                                 'balance' => $balance, 'account' => $v->source_bank_account_id, 'mode' => $v->mode_of_payment];
                @endphp
                <tr class="group cursor-pointer transition-colors hover:bg-slate-50/60"
                    @click="window.location = '{{ route('vouchers.show', $v->id) }}'">

                    {{-- Voucher no. --}}
                    <td class="border-b border-slate-100 px-4 py-2.5 align-middle text-[12.5px] font-semibold text-slate-700 whitespace-nowrap">
                        {{ $v->voucher_no }}
                    </td>

                    {{-- Payee --}}
                    <td class="border-b border-slate-100 px-4 py-2.5 align-middle">
                        <span class="block text-[13px] font-medium text-slate-800">{{ $v->payee_name }}</span>
                        @if ($v->sourceBankAccount)
                            <span class="block text-[10.5px] text-slate-400">via {{ $v->sourceBankAccount->name }}</span>
                        @endif
                    </td>

                    {{-- Project --}}
                    <td class="border-b border-slate-100 px-4 py-2.5 align-middle text-[12.5px] text-slate-600">
                        {{ $v->project?->name ?? '—' }}
                    </td>

                    {{-- Voucher date --}}
                    <td class="border-b border-slate-100 px-4 py-2.5 align-middle tabular-nums text-[12px] text-slate-500 whitespace-nowrap">
                        {{ $v->voucher_date->format('M d, Y') }}
                    </td>

                    {{-- Due date --}}
                    <td class="border-b border-slate-100 px-4 py-2.5 align-middle tabular-nums text-[12px] whitespace-nowrap {{ $overdue ? 'font-semibold text-rose-600' : 'text-slate-600' }}">
                        {{ $v->due_date?->format('M d, Y') ?? '—' }}
                        @if ($days !== null)
                            <span class="block text-[10px] font-normal {{ $overdue ? 'text-rose-400' : 'text-slate-400' }}">
                                {{ $overdue ? abs($days) . 'd ago' : 'in ' . $days . 'd' }}
                            </span>
                        @endif
                    </td>

                    {{-- Aging chip --}}
                    <td class="border-b border-slate-100 px-4 py-2.5 align-middle">
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10.5px] font-semibold ring-1 ring-inset {{ $cfg['badge'] }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $bucketConfig[$bucket]['dot'] ?? 'bg-slate-300' }}"></span>
                            {{ $cfg['label'] }}
                        </span>
                    </td>

                    {{-- Type --}}
                    <td class="border-b border-slate-100 px-4 py-2.5 align-middle text-[11.5px] text-slate-500">
                        {{ $v->typeLabel() }}
                    </td>

                    {{-- Payable --}}
                    <td class="border-b border-slate-100 px-4 py-2.5 align-middle text-right tabular-nums text-[12.5px] text-slate-600 whitespace-nowrap">
                        {{ $peso($v->amount_payable) }}
                    </td>

                    {{-- Balance due --}}
                    <td class="border-b border-slate-100 px-4 py-2.5 align-middle text-right tabular-nums text-[12.5px] font-semibold whitespace-nowrap {{ $balance > 0 ? 'text-amber-700' : 'text-emerald-600' }}">
                        {{ $peso($balance) }}
                    </td>

                    {{-- Action --}}
                    <td class="sticky right-0 z-10 border-b border-slate-100 bg-white px-3 py-2.5 align-middle group-hover:bg-slate-50" @click.stop>
                        <div class="flex items-center justify-end gap-1.5">
                            <button type="button" @click="openPay({{ \Illuminate\Support\Js::from($payload) }})"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100">
                                <i data-lucide="banknote" class="h-3 w-3 pointer-events-none"></i> Pay
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-6 py-16 text-center">
                        <i data-lucide="check-circle-2" class="mx-auto mb-2 h-9 w-9 text-emerald-200"></i>
                        <p class="text-sm font-medium text-slate-400">No open payables{{ $activeBucket ? ' in this bucket' : '' }}.</p>
                        <p class="mt-0.5 text-xs text-slate-300">You're all caught up.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>

        {{-- Sticky footer totals row --}}
        @if ($rows->isNotEmpty())
        <tfoot class="sticky bottom-0 bg-slate-50">
            <tr>
                <td colspan="7" class="border-t border-slate-200 px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    Total{{ $activeBucket ? ' · ' . ($bucketConfig[$activeBucket]['label'] ?? '') : ' outstanding' }}
                </td>
                <td class="border-t border-slate-200 px-4 py-2 text-right tabular-nums text-[12.5px] font-bold text-slate-600 whitespace-nowrap">
                    {{ $peso($rows->sum(fn ($v) => (float) $v->amount_payable)) }}
                </td>
                <td class="border-t border-slate-200 px-4 py-2 text-right tabular-nums text-[12.5px] font-bold text-amber-700 whitespace-nowrap">
                    {{ $peso($summary['outstanding']) }}
                </td>
                <td class="border-t border-slate-200"></td>
            </tr>
        </tfoot>
        @endif
    </table>
    <x-pagination-simple :paginator="$rows" />
</div>