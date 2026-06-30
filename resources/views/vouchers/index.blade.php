<x-app-layout page-title="Daily Transactions">
@php
    $peso = fn ($n) => '₱' . number_format((float) $n, 2);
    $isAccountingUser = auth()->user()->isAccounting();

    $statusTone = [
        'draft'     => 'bg-slate-100 text-slate-600 ring-slate-200',
        'unpaid'    => 'bg-amber-50 text-amber-800 ring-amber-100',
        'partial'   => 'bg-blue-50 text-blue-700 ring-blue-100',
        'pdc'       => 'bg-violet-50 text-violet-700 ring-violet-100',
        'paid'      => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        'cancelled' => 'bg-rose-50 text-rose-600 ring-rose-100',
    ];
@endphp

<script>
document.addEventListener('alpine:init', () => {
    const searchMixin = typeof window.disburseListSearchMixin === 'function'
        ? window.disburseListSearchMixin()
        : (typeof window.disburseListSearchFallback === 'function' ? window.disburseListSearchFallback() : {});

    Alpine.data('vouchersPage', () => ({
        ...searchMixin,
        showPay: @json($errors->any() && old('paying_voucher_id')),
        activeProjectId: @json($activeProject?->id),
        payVoucher: { id: null, no: '', payee: '', balance: 0 },
        p: { bank_account_id: '', paid_on: @json(now()->format('Y-m-d')), amount: '', mode: 'cash', check_no: '', check_date: '', notes: '' },
        openAdd() {
            window.location.href = '{{ route('vouchers.create') }}' + (this.activeProjectId ? '?project_id=' + this.activeProjectId : '');
        },
        openPay(v) {
            this.payVoucher = { id: v.id, no: v.voucher_no, payee: v.payee_name, balance: v.balance };
            this.p = {
                bank_account_id: v.source_bank_account_id ? String(v.source_bank_account_id) : '',
                paid_on: @json(now()->format('Y-m-d')),
                amount: v.balance > 0 ? String(v.balance) : '',
                mode: v.mode_of_payment || 'cash',
                check_no: '', check_date: '', notes: '',
            };
            this.showPay = true;
        },
        closePay() { this.showPay = false; },
    }));
});
</script>

<div x-data="vouchersPage" class="disburse-page">

@if (session('success'))
    <div class="flex shrink-0 items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs font-medium text-green-800">
        <i data-lucide="check-circle-2" class="h-3.5 w-3.5 shrink-0 text-green-600"></i>
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="shrink-0 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
        <p class="font-semibold">Please fix the following:</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- ── Header ───────────────────────────────────────────────────────────── --}}
<div class="disburse-page-header">
    <div class="min-w-0">
        @if ($activeProject)
            <a href="{{ route('projects.show.outflow', $activeProject) }}"
               class="mb-0.5 inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 transition hover:text-omet-navy">
                <i data-lucide="arrow-left" class="h-3 w-3"></i> Back to {{ $activeProject->name }}
            </a>
        @endif
        <h1 class="text-xl font-bold tracking-tight text-omet-navy">Daily Transactions</h1>
        <p class="text-xs text-slate-500 flex flex-wrap items-center gap-1.5">
            <span><span data-disburse-result-count>{{ $summary['count'] }}</span> <span data-disburse-result-mode>{{ $activeSearch ? 'matching' : 'shown' }}</span> · {{ $peso($summary['outstanding']) }} outstanding</span>
            @if ($activeSearch)
                <a href="{{ route('vouchers.index', array_filter([
                    'status' => $activeStatus,
                    'source' => $activeSource,
                    'date_from' => $activeDateFrom,
                    'date_to' => $activeDateTo,
                    'project_id' => $activeProject?->id,
                ])) }}"
                   data-disburse-search-clear
                   class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 hover:bg-slate-200">
                    <i data-lucide="x" class="h-2.5 w-2.5"></i> Clear search
                </a>
            @endif
            @if ($activeProject)
                <span class="inline-flex items-center gap-1 rounded-full bg-omet-blue/10 px-2 py-0.5 text-[11px] font-semibold text-omet-blue">
                    <i data-lucide="folder" class="h-3 w-3"></i> {{ $activeProject->name }}
                    <a href="{{ route('vouchers.index') }}" class="ml-0.5 rounded-full p-0.5 text-omet-blue/60 transition hover:bg-omet-blue/10 hover:text-omet-blue" title="Clear project filter">
                        <i data-lucide="x" class="h-2.5 w-2.5"></i>
                    </a>
                </span>
            @endif
        </p>
    </div>
    <div class="disburse-page-actions">
        <a href="{{ route('vouchers.payables') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-omet-blue hover:text-omet-blue">
            <i data-lucide="alarm-clock" class="h-4 w-4"></i> Payables
        </a>
        <a href="{{ route('vouchers.create', $activeProject ? ['project_id' => $activeProject->id] : []) }}"
           class="inline-flex items-center gap-1.5 rounded-lg bg-omet-blue px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-omet-lightblue">
            <i data-lucide="plus" class="h-4 w-4"></i>
            <span class="sm:hidden">Add</span>
            <span class="hidden sm:inline">Add Voucher</span>
        </a>
    </div>
</div>

{{-- ── Summary cards — company-wide totals, not relevant to Accounting Staff's own-vouchers view ── --}}
@unless ($isAccountingUser)
<div class="disburse-kpi-grid">
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Total transaction</p>
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-omet-blue/5"><i data-lucide="receipt" class="h-3.5 w-3.5 text-omet-blue"></i></span>
        </div>
        <p class="mt-2 text-lg font-bold tabular-nums text-omet-navy">{{ $peso($summary['payable']) }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Paid</p>
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-emerald-50"><i data-lucide="check-check" class="h-3.5 w-3.5 text-emerald-600"></i></span>
        </div>
        <p class="mt-2 text-lg font-bold tabular-nums text-emerald-700">{{ $peso($summary['paid']) }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Outstanding</p>
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-amber-50"><i data-lucide="hourglass" class="h-3.5 w-3.5 text-amber-600"></i></span>
        </div>
        <p class="mt-2 text-lg font-bold tabular-nums text-amber-700">{{ $peso($summary['outstanding']) }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Overdue</p>
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-rose-50"><i data-lucide="alert-triangle" class="h-3.5 w-3.5 text-rose-600"></i></span>
        </div>
        <p class="mt-2 text-lg font-bold tabular-nums text-rose-600">{{ $summary['overdue'] }}</p>
    </div>
</div>
@endunless

{{-- ── Toolbar ──────────────────────────────────────────────────────────── --}}
<div class="disburse-toolbar">
    <form method="GET" action="{{ route('vouchers.index') }}" class="disburse-filter-form w-full" id="filter-form">
        <div class="disburse-search relative min-w-[12rem] flex-1 sm:max-w-xs">
            <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
            <input type="search" name="q" value="{{ $activeSearch }}" autocomplete="off"
                   placeholder="Search payee, number, project, category…"
                   @input="onSearchInput($event)"
                   @keydown="onSearchKeydown($event)"
                   class="h-9 w-full rounded-md border border-slate-200 bg-white pl-8 pr-7 text-[12.5px] text-slate-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
            @if ($activeSearch)
                <a href="{{ route('vouchers.index', array_filter([
                    'status' => $activeStatus,
                    'source' => $activeSource,
                    'date_from' => $activeDateFrom,
                    'date_to' => $activeDateTo,
                    'project_id' => $activeProject?->id,
                ])) }}"
                   data-disburse-search-clear
                   class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                   aria-label="Clear search">
                    <i data-lucide="x" class="h-3 w-3"></i>
                </a>
            @endif
        </div>
        @if ($activeProject)
            <input type="hidden" name="project_id" value="{{ $activeProject->id }}">
        @endif

        {{-- Date from --}}
        <div class="relative flex items-center">
            <i data-lucide="calendar" class="pointer-events-none absolute left-2.5 h-3.5 w-3.5 text-slate-400"></i>
            <input type="date" name="date_from" value="{{ $activeDateFrom }}"
                   onchange="this.form.submit()"
                   title="From date"
                   class="h-9 rounded-lg border border-slate-200 bg-white pl-8 pr-2 text-[12px] text-slate-700 shadow-sm outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 w-[140px]">
        </div>
        <span class="hidden text-center text-[11px] text-slate-400 sm:inline">to</span>
        <input type="date" name="date_to" value="{{ $activeDateTo }}"
               onchange="this.form.submit()"
               title="To date"
               class="h-9 rounded-lg border border-slate-200 bg-white px-2 text-[12px] text-slate-700 shadow-sm outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 w-[140px]">

        {{-- Source filter --}}
        <div class="relative">
            <select name="source" onchange="this.form.submit()"
                    class="h-9 appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-[12px] text-slate-700 shadow-sm outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                <option value="">All sources</option>
                @foreach ($sources as $k => $label)
                    <option value="{{ $k }}" @selected($activeSource === $k)>{{ $label }}</option>
                @endforeach
            </select>
            <i data-lucide="chevron-down" class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        </div>

        {{-- Status filter --}}
        <div class="relative">
            <select name="status" onchange="this.form.submit()"
                    class="h-9 appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-[12px] text-slate-700 shadow-sm outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                <option value="">All statuses</option>
                @foreach ($statuses as $k => $label)
                    <option value="{{ $k }}" @selected($activeStatus === $k)>{{ $label }}</option>
                @endforeach
            </select>
            <i data-lucide="chevron-down" class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        </div>

        @if ($activeStatus || $activeSource || $activeDateFrom || $activeDateTo || $activeSearch)
            <a href="{{ route('vouchers.index', $activeProject ? ['project_id' => $activeProject->id] : []) }}"
               class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <i data-lucide="x" class="h-3 w-3"></i> Clear filters
            </a>
        @endif
    </form>
</div>

{{-- ── Table ────────────────────────────────────────────────────────────── --}}
@include('vouchers.partials.index-table')

@include('vouchers.partials.payment-modal')

</div>
</x-app-layout>
