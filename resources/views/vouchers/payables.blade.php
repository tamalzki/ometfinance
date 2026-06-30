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
    const searchMixin = typeof window.disburseListSearchMixin === 'function'
        ? window.disburseListSearchMixin()
        : (typeof window.disburseListSearchFallback === 'function' ? window.disburseListSearchFallback() : {});

    Alpine.data('payablesPage', () => ({
        ...searchMixin,
        showPay: @json($errors->any() && ! $errors->has('cancel')),
        payVoucher: { id: null, no: '', payee: '', balance: 0 },
        p: { bank_account_id: '', paid_on: @json(now()->format('Y-m-d')), amount: '', mode: 'cash', check_no: '', check_date: '', notes: '' },
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
        <p class="mt-0.5 text-[11px] text-slate-400">
            <span data-disburse-result-count>{{ $activeSearch ? $rows->total() : $summary['count'] }}</span>
            @if ($activeSearch)
                <span data-disburse-result-mode>matching</span> · {{ $summary['count'] }} open total
            @else
                open {{ \Illuminate\Support\Str::plural('voucher', $summary['count']) }}
            @endif
        </p>
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
    <form method="GET" action="{{ route('vouchers.payables') }}" class="disburse-search relative min-w-[12rem] sm:max-w-xs">
        @if ($activeBucket)
            <input type="hidden" name="bucket" value="{{ $activeBucket }}">
        @endif
        <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        <input type="search" name="q" value="{{ $activeSearch }}" autocomplete="off"
               placeholder="Search payee, voucher no., project…"
               @input="onSearchInput($event)"
               @keydown="onSearchKeydown($event)"
               class="h-8 w-full rounded-md border border-slate-200 bg-white pl-8 pr-7 text-[12px] text-slate-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
        @if ($activeSearch)
            <a href="{{ route('vouchers.payables', array_filter(['bucket' => $activeBucket])) }}"
               class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
               aria-label="Clear search">
                <i data-lucide="x" class="h-3 w-3"></i>
            </a>
        @endif
    </form>

    {{-- Divider --}}
    <div class="h-5 w-px bg-slate-200 hidden sm:block"></div>

    {{-- Aging bucket chips --}}
    <div class="flex flex-wrap items-center gap-1.5">
        @if ($activeBucket)
            <a href="{{ route('vouchers.payables', array_filter(['q' => $activeSearch ?: null])) }}"
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
            <a href="{{ $isActive ? route('vouchers.payables', array_filter(['q' => $activeSearch ?: null])) : route('vouchers.payables', array_filter(['bucket' => $key, 'q' => $activeSearch ?: null])) }}"
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
@include('vouchers.partials.payables-table')

@include('vouchers.partials.payment-modal')

</div>
</x-app-layout>
