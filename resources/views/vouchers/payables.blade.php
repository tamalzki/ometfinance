<x-app-layout page-title="Payables">
@php
    $peso = fn ($n) => '₱' . number_format((float) $n, 2);

    $bucketConfig = [
        'current'  => ['label' => 'Current',          'dot' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-100'],
        'd1_30'    => ['label' => '1–30d overdue',    'dot' => 'bg-amber-400',   'text' => 'text-amber-700',   'badge' => 'bg-amber-50 text-amber-800 ring-amber-100'],
        'd31_60'   => ['label' => '31–60d overdue',   'dot' => 'bg-orange-500',  'text' => 'text-orange-700',  'badge' => 'bg-orange-50 text-orange-700 ring-orange-100'],
        'd60_plus' => ['label' => '60+ days overdue', 'dot' => 'bg-rose-500',    'text' => 'text-rose-700',    'badge' => 'bg-rose-50 text-rose-700 ring-rose-100'],
        'pdc'      => ['label' => 'PDC',               'dot' => 'bg-violet-400',  'text' => 'text-violet-700',  'badge' => 'bg-violet-50 text-violet-700 ring-violet-100'],
        'no_term'  => ['label' => 'No due date',       'dot' => 'bg-slate-300',   'text' => 'text-slate-600',   'badge' => 'bg-slate-100 text-slate-600 ring-slate-200'],
    ];
@endphp

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('payablesPage', () => ({
        showPay: @json($errors->any() && ! $errors->has('cancel')),
        q: '',
        rowSearchIndex: @json($rowSearchIndex),
        payVoucher: { id: null, no: '', payee: '', balance: 0 },
        p: { bank_account_id: '', paid_on: @json(now()->format('Y-m-d')), amount: '', mode: 'cash', check_no: '', check_date: '', notes: '' },
        get needle() {
            return (this.q || '').trim().toLowerCase();
        },
        rowVisible(id) {
            if (! this.needle) return true;
            const hay = this.rowSearchIndex[id];
            return hay && hay.includes(this.needle);
        },
        openPay(v) {
            this.payVoucher = { id: v.id, no: v.no, payee: v.payee, balance: v.balance };
            this.p = { bank_account_id: v.account ? String(v.account) : '', paid_on: @json(now()->format('Y-m-d')),
                       amount: v.balance > 0 ? String(v.balance) : '', mode: v.mode || 'cash', check_no: '', check_date: '', notes: '' };
            this.showPay = true;
        },
        closePay() { this.showPay = false; },
    }));
});
</script>

<div x-data="payablesPage" class="disburse-page">

{{-- Flash / errors --}}
@if (session('success'))
    <div class="flex shrink-0 items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs font-medium text-green-800">
        <i data-lucide="check-circle-2" class="h-3.5 w-3.5 shrink-0 text-green-600"></i> {{ session('success') }}
    </div>
@endif
@if ($errors->any())
    <div class="shrink-0 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
        <ul class="list-inside list-disc">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

{{-- ── Top bar: title + KPIs + action ─────────────────────────────────────── --}}
<div class="disburse-summary-strip">
    {{-- Title block --}}
    <div class="disburse-summary-title flex flex-col justify-center">
        <p class="text-[13px] font-bold tracking-tight text-omet-navy">Payables</p>
        <p class="mt-0.5 text-[11px] text-slate-400">{{ $summary['count'] }} open {{ \Illuminate\Support\Str::plural('voucher', $summary['count']) }}</p>
    </div>

    {{-- Outstanding --}}
    <div class="flex flex-col justify-center">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Outstanding</p>
        <p class="mt-1 text-base font-bold tabular-nums text-amber-700">{{ $peso($summary['outstanding']) }}</p>
    </div>

    {{-- Overdue --}}
    <div class="flex flex-col justify-center">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Overdue</p>
        <p class="mt-1 text-base font-bold tabular-nums {{ $summary['overdue'] > 0 ? 'text-rose-600' : 'text-slate-300' }}">{{ $peso($summary['overdue']) }}</p>
    </div>

    {{-- Due in 7 days --}}
    <div class="flex flex-col justify-center">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Due in 7 days</p>
        <p class="mt-1 text-base font-bold tabular-nums {{ $summary['due_7d'] > 0 ? 'text-orange-600' : 'text-slate-300' }}">{{ $peso($summary['due_7d']) }}</p>
    </div>

    {{-- Action --}}
    <div class="disburse-summary-action">
        <a href="{{ route('vouchers.index') }}"
           class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-semibold text-slate-600 shadow-sm transition hover:border-omet-blue hover:text-omet-blue sm:w-auto">
            <i data-lucide="receipt" class="h-3.5 w-3.5"></i> All vouchers
        </a>
    </div>
</div>

{{-- ── Toolbar: search + aging chip filters ────────────────────────────────── --}}
<div class="flex shrink-0 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
    {{-- Search --}}
    <div class="disburse-search">
        <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        <input type="search" x-model="q" autocomplete="off" placeholder="Search payee, voucher no., project…"
               class="h-8 rounded-md border border-slate-200 bg-white pl-8 pr-3 text-[12px] text-slate-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
    </div>

    {{-- Divider --}}
    <div class="h-5 w-px bg-slate-200 hidden sm:block"></div>

    {{-- Aging bucket chips --}}
    <div class="flex flex-wrap items-center gap-1.5">
        @if ($activeBucket)
            <a href="{{ route('vouchers.payables') }}"
               class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-2.5 py-0.5 text-[11px] font-medium text-slate-500 transition hover:bg-slate-100">
                <i data-lucide="x" class="h-2.5 w-2.5"></i> All
            </a>
        @else
            <span class="text-[11px] font-medium text-slate-400">Filter:</span>
        @endif

        @foreach ($bucketConfig as $key => $cfg)
            @php
                $b = $buckets[$key];
                $isActive = $activeBucket === $key;
            @endphp
            <a href="{{ $isActive ? route('vouchers.payables') : route('vouchers.payables', ['bucket' => $key]) }}"
               @class([
                   'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 transition',
                   $cfg['badge'] . ' ring-inset' => $isActive,
                   'border border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700' => ! $isActive,
                   'opacity-40 pointer-events-none' => $b['count'] === 0 && ! $isActive,
               ])>
                <span class="h-1.5 w-1.5 rounded-full {{ $cfg['dot'] }}"></span>
                {{ $cfg['label'] }}
                @if ($b['count'] > 0)
                    <span class="font-bold">{{ $b['count'] }}</span>
                @endif
            </a>
        @endforeach
    </div>
</div>

{{-- ── Table — this is the main event ─────────────────────────────────────── --}}
<div class="disburse-data-grid">
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
                    x-show="rowVisible({{ $v->id }})"
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

@include('vouchers.partials.payment-modal')

</div>
</x-app-layout>
