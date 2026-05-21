<x-app-layout page-title="Accounts">
@php
    /* ── Data for Alpine ── */
    $accountsJson = $allAccounts->map(fn ($a) => [
        'id'          => $a->id,
        'label'       => ($a->entity?->name ?? '?') . ' — ' . $a->name,
        'entity_slug' => $a->entity?->slug ?? '',
        'entity_name' => $a->entity?->name ?? '',
        'bank'        => $a->bank_name,
        'balance'     => $a->currentBalance(),
        'search'      => strtolower(implode(' ', array_filter([
            $a->entity?->name, $a->name, $a->bank_name, (string) ($a->account_number ?? ''),
        ]))),
    ])->values();

    $entitiesJson = $entities->map(fn ($e) => [
        'id'    => $e->id,
        'name'  => $e->name,
        'slug'  => $e->slug,
        'color' => $e->color,
        'total' => (float) $e->computed_total,
    ])->values();

    $activeAccountId = $activeAccount?->id;
@endphp

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('accountsHub', () => ({
        allAccounts:  @json($accountsJson),
        allEntities:  @json($entitiesJson),
        entityFilter: 'all',

        comboOpen:  false,
        comboQuery: '',

        showEntry:       false,
        showTransfer:    false,
        showAddAccount:  false,
        showEditAccount: false,
        showEditEntry:   false,

        overviewLayout: (() => {
            try {
                return localStorage.getItem('accounts-overview-layout') === 'list' ? 'list' : 'grid';
            } catch (e) {
                return 'grid';
            }
        })(),

        overviewAccountSearch: '',

        ledgerSearch: '',

        entryType: 'out',
        entryDate: @json(now()->format('Y-m-d')),

        editId: null, editDate: '', editDescription: '',
        editType: 'out', editAmount: '', editNotes: '', editAction: '',

        get filteredAccounts() {
            const q = this.comboQuery.toLowerCase().trim();
            return this.allAccounts.filter(a => {
                const entityOk = this.entityFilter === 'all' || a.entity_slug === this.entityFilter;
                const searchOk = !q || a.search.includes(q);
                return entityOk && searchOk;
            });
        },

        fmtBalance(n) {
            const num = Number(n) || 0;
            return '₱' + Math.abs(num).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        pickAccount(id) {
            const url = new URL(window.location.href);
            url.searchParams.set('account_id', id);
            url.searchParams.delete('from');
            url.searchParams.delete('to');
            window.location.href = url.toString();
        },

        focusEntity(slug) {
            this.entityFilter = slug;
            this.comboOpen = true;
            this.$nextTick(() => this.$refs.comboQ?.focus());
        },

        overviewQueryNorm() {
            return String(this.overviewAccountSearch ?? '').toLowerCase().trim();
        },

        overviewEntitySlugVisible(slug) {
            const q = this.overviewQueryNorm();
            if (!q) {
                return true;
            }

            return this.allAccounts.some(
                (a) => a.entity_slug === slug && a.search.includes(q)
            );
        },

        overviewAccountRowVisible(accountId) {
            const q = this.overviewQueryNorm();
            if (!q) {
                return true;
            }

            const hit = this.allAccounts.find((a) => Number(a.id) === Number(accountId));
            return !!(hit && hit.search.includes(q));
        },

        get overviewSearchHasHits() {
            const q = this.overviewQueryNorm();
            if (!q) {
                return true;
            }

            return this.allAccounts.some((a) => a.search.includes(q));
        },

        get overviewHasSearchQuery() {
            return this.overviewQueryNorm().length > 0;
        },

        ledgerVisible(text) {
            const q = (this.ledgerSearch || '').toLowerCase().trim();
            if (!q) return true;
            return text.includes(q);
        },

        openEdit(entry, action) {
            this.editId          = entry.id;
            this.editDate        = entry.date;
            this.editDescription = entry.description;
            this.editType        = entry.amount_out ? 'out' : 'in';
            this.editAmount      = entry.amount_out ?? entry.amount_in ?? '';
            this.editNotes       = entry.notes ?? '';
            this.editAction      = action;
            this.showEditEntry   = true;
        },

        openAddAccount() {
            this.showAddAccount = true;
            this.$nextTick(() => {
                const sel = this.$refs.addAccountEntity;
                if (!sel || this.entityFilter === 'all') return;
                const ent = this.allEntities.find(e => e.slug === this.entityFilter);
                if (ent) sel.value = String(ent.id);
            });
        },

        init() {
            this.$watch('overviewLayout', value => {
                try {
                    localStorage.setItem('accounts-overview-layout', value);
                } catch (e) { /* private mode */ }
            });
        },
    }));
});
</script>

<div x-data="accountsHub" class="accounts-hub flex min-h-0 flex-1 flex-col gap-5 pb-6 text-slate-800 lg:gap-6 lg:pb-8">

{{-- ── Flash ──────────────────────────────────────────────────────────────── --}}
@if (session('success'))
    <div class="mb-4 flex shrink-0 items-center gap-2 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-xs font-medium text-green-800">
        <i data-lucide="check-circle-2" class="h-3.5 w-3.5 shrink-0 text-green-500"></i>
        {{ session('success') }}
    </div>
@endif
@if ($errors->any())
    <div class="mb-4 shrink-0 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
        <ul class="list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
@endif

<div class="accounts-hub-shell flex min-h-0 w-full max-w-[1680px] flex-1 flex-col gap-4 lg:gap-5">

{{-- ── Top row (overview only — hidden when viewing a specific account) ───── --}}
@unless(isset($activeAccount))
<div class="accounts-hub-page-head flex shrink-0 flex-wrap items-end justify-between gap-4 lg:gap-5">
    <div class="accounts-hub-headline min-w-[12rem]">
        <p class="accounts-hub-eyebrow">Overview</p>
        <h1 class="accounts-hub-heading mt-1.5">Accounts</h1>
        <p class="accounts-hub-subhead mt-1.5 max-w-md text-[13px] leading-relaxed text-slate-500">
            Balances by category — pick an account to open its ledger.
        </p>
    </div>
    <div class="accounts-hub-actions flex shrink-0 flex-wrap items-center gap-2 lg:gap-3">
        <div>
            <div class="accounts-hub-overview-search-inline flex min-w-0 flex-wrap items-center gap-1.5 sm:gap-2"
                 :class="{ 'accounts-hub-overview-search-inline--list': overviewLayout === 'list' }">
                <label for="overview-account-search" class="sr-only">Search Account</label>
                <div class="relative shrink-0">
                    <span class="pointer-events-none absolute left-2 top-1/2 z-[1] -translate-y-1/2 text-slate-400"
                          aria-hidden="true">
                        <i data-lucide="search" class="h-3 w-3 shrink-0"></i>
                    </span>
                    <input id="overview-account-search"
                           type="search"
                           autocomplete="off"
                           inputmode="search"
                           placeholder="Search Account"
                           x-model="overviewAccountSearch"
                           :class="{ 'accounts-hub-overview-search-input--compact-list': overviewLayout === 'list' }"
                           class="accounts-hub-overview-search-input accounts-hub-overview-search-input--compact placeholder:text-slate-400 w-[9.5rem] min-w-[7.5rem] sm:w-[11rem]"
                           @keydown.escape.prevent="overviewAccountSearch = ''"/>
                    <button type="button"
                            class="absolute right-0.5 top-1/2 z-[1] inline-flex size-7 -translate-y-1/2 items-center justify-center rounded text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 sm:right-px"
                            aria-label="Clear account search"
                            x-show="overviewHasSearchQuery"
                            x-cloak
                            tabindex="-1"
                            @click.prevent="overviewAccountSearch = ''">
                        <i data-lucide="x" class="h-3.5 w-3.5"></i>
                    </button>
                </div>
                <span class="sr-only" aria-live="polite" x-text="overviewLayout === 'list' ? 'List view selected' : 'Grid view selected'"></span>
                <div class="accounts-hub-view-toggle shrink-0" role="group" aria-label="Accounts layout">
                    <button type="button"
                            class="accounts-hub-view-toggle-btn"
                            @click="overviewLayout = 'grid'"
                            :aria-pressed="overviewLayout === 'grid'"
                            title="Grid view">
                        <i data-lucide="layout-grid" class="h-4 w-4 shrink-0"></i>
                        <span>Grid</span>
                    </button>
                    <button type="button"
                            class="accounts-hub-view-toggle-btn"
                            @click="overviewLayout = 'list'"
                            :aria-pressed="overviewLayout === 'list'"
                            title="List view">
                        <i data-lucide="list" class="h-4 w-4 shrink-0"></i>
                        <span>List</span>
                    </button>
                </div>
            </div>
        </div>
        <button type="button" @click="openAddAccount()" class="accounts-hub-btn-primary">
            <i data-lucide="plus" class="h-4 w-4 shrink-0 opacity-95"></i>
            Add account
        </button>
    </div>
</div>
@endunless

{{-- ── Account toolbar (only visible when an account is open) ───────────── --}}
@isset($activeAccount)
@php $currentBal = $activeAccount->currentBalance(); @endphp

{{-- Back to Accounts --}}
<a href="{{ route('accounts.overall') }}"
   class="-mb-1 inline-flex w-fit shrink-0 items-center gap-1.5 text-[12px] font-medium text-slate-500 transition hover:text-omet-navy">
    <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
    Accounts
</a>

<div class="accounts-hub-toolbar mb-3 flex shrink-0 flex-col gap-3 text-slate-800">

    {{-- Row 1: Account picker + Balance ──────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-4">

        {{-- Account combobox --}}
        <div class="relative min-w-0 flex-1" @click.outside="comboOpen = false">
            <button type="button"
                    @click="comboOpen = !comboOpen; if (comboOpen) $nextTick(() => $refs.comboQ?.focus())"
                    class="group flex w-full min-w-0 max-w-md items-center gap-3 rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-left shadow-sm outline-none transition hover:border-slate-300 focus-visible:border-[#185FA5]/40 focus-visible:ring-2 focus-visible:ring-[#185FA5]/35">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-[#185FA5]/8 text-[#185FA5]">
                    <i data-lucide="landmark" class="h-4 w-4"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[13.5px] font-semibold text-omet-navy">
                        {{ $activeAccount->entity?->name }} — {{ $activeAccount->name }}
                    </span>
                    @if ($activeAccount->bank_name)
                    <span class="block truncate text-[11px] text-slate-400">{{ $activeAccount->bank_name }}</span>
                    @endif
                </span>
                <i data-lucide="chevrons-up-down" class="h-4 w-4 shrink-0 text-slate-400 transition group-hover:text-slate-600"></i>
            </button>

            {{-- Dropdown --}}
            <div x-show="comboOpen" x-cloak
                 class="absolute left-0 top-full z-40 mt-1 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
                <div class="border-b border-slate-100 p-2">
                    <input x-ref="comboQ" x-model="comboQuery" type="text"
                           placeholder="Search by name, bank, entity…"
                           class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[12px] text-gray-800 outline-none focus:border-[#185FA5] focus:bg-white"
                           @keydown.escape.prevent="comboOpen = false">
                </div>
                <div class="max-h-64 overflow-y-auto py-1">
                    <template x-for="a in filteredAccounts" :key="a.id">
                        <button type="button"
                                @click="pickAccount(a.id); comboOpen = false; comboQuery = ''"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-[12px] transition-colors duration-150 hover:bg-[#EFF6FF] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#185FA5]/25"
                                :class="a.id === {{ $activeAccountId ?? 'null' }} ? 'bg-blue-50/70 text-[#185FA5]' : 'text-gray-700'">
                            <span class="min-w-0">
                                <span class="block truncate font-medium" x-text="a.label"></span>
                                <span class="block text-[10.5px] text-gray-400" x-text="a.bank"></span>
                            </span>
                            <span class="shrink-0 tabular-nums text-[11.5px] font-semibold"
                                  :class="a.balance < 0 ? 'text-red-500' : 'text-gray-600'"
                                  x-text="fmtBalance(a.balance)"></span>
                        </button>
                    </template>
                    <p x-show="filteredAccounts.length === 0"
                       class="px-3 py-5 text-center text-[11px] text-gray-400">No accounts match.</p>
                </div>
            </div>
        </div>

        {{-- Balance (prominent) --}}
        <div class="shrink-0 text-right">
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">Current balance</p>
            <p class="mt-0.5 text-[22px] font-bold tabular-nums leading-none {{ $currentBal < 0 ? 'text-red-600' : 'text-omet-navy' }}">
                ₱{{ number_format($currentBal, 2) }}
            </p>
        </div>
    </div>

    {{-- ── Actions row ────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
        <button type="button"
                @click="showEntry = true; entryType = 'out'; entryDate = @js(now()->format('Y-m-d'))"
                class="accounts-hub-btn-primary px-4 text-[12px]">
            <i data-lucide="plus" class="h-3.5 w-3.5"></i>
            Add Entry
        </button>
        <button type="button" @click="showTransfer = true"
                class="accounts-hub-btn-secondary px-4 text-[12px]">
            <i data-lucide="arrow-left-right" class="h-3.5 w-3.5"></i>
            Transfer
        </button>
        <button type="button" @click="showEditAccount = true"
                class="accounts-hub-btn-secondary px-4 text-[12px]">
            <i data-lucide="pencil-line" class="h-3.5 w-3.5"></i>
            Edit Account
        </button>
    </div>

    {{-- ── Filters row: Search + Period ───────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">

        {{-- Search entries --}}
        <div class="relative w-full max-w-xs">
            <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
            <input x-model="ledgerSearch"
                   type="search"
                   placeholder="Search entries"
                   aria-label="Search ledger entries"
                   class="h-9 w-full rounded-md border border-slate-200 bg-white pl-8 pr-7 text-[12.5px] text-slate-700 outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/15"
                   @keydown.escape.prevent="ledgerSearch = ''">
            <button type="button"
                    x-show="ledgerSearch"
                    x-cloak
                    @click="ledgerSearch = ''"
                    class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Clear search">
                <i data-lucide="x" class="h-3 w-3"></i>
            </button>
        </div>

        {{-- Date filter --}}
        <form method="GET" action="{{ route('accounts.overall') }}"
              class="flex flex-wrap items-center gap-1.5">
            <input type="hidden" name="account_id" value="{{ $activeAccount->id }}">
            <label for="filter-from" class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                <i data-lucide="calendar-range" class="h-3.5 w-3.5"></i>
                Period
            </label>
            <input id="filter-from" type="date" name="from" value="{{ $from ?? '' }}" onchange="this.form.submit()"
                   class="h-9 min-w-[8.5rem] rounded-lg border border-slate-200 bg-white px-3 text-[12px] text-slate-700 shadow-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/15">
            <span class="text-[11px] text-slate-300">→</span>
            <input type="date" name="to" value="{{ $to ?? '' }}" onchange="this.form.submit()"
                   class="h-9 min-w-[8.5rem] rounded-lg border border-slate-200 bg-white px-3 text-[12px] text-slate-700 shadow-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/15">
            @if ($from || $to)
                <a href="{{ route('accounts.overall', ['account_id' => $activeAccount->id]) }}"
                   class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                   title="Clear date filter">
                    <i data-lucide="x" class="h-3 w-3"></i>
                    Clear
                </a>
            @endif
        </form>
    </div>
</div>
@endisset

{{-- ── Main content area ──────────────────────────────────────────────────── --}}
@isset($activeAccount)

    {{-- ── Ledger table ─────────────────────────────────────────────────── --}}
    <div class="accounts-hub-sheet flex min-h-0 flex-1 flex-col text-slate-800">
        @if ($entries->isEmpty())
            <div class="flex flex-1 flex-col items-center justify-center py-20 text-center">
                <i data-lucide="file-spreadsheet" class="mb-3 h-12 w-12 text-gray-200"></i>
                <p class="text-sm font-semibold text-gray-400">No entries yet</p>
                <p class="mt-1 text-xs text-gray-300">
                    @if ($from || $to)
                        No entries in this date range.
                        <a href="{{ route('accounts.overall', ['account_id' => $activeAccount->id]) }}"
                           class="font-medium text-[#185FA5] underline-offset-2 hover:underline">Clear filter</a>
                    @else
                        Click <strong>Add Entry</strong> to record the first transaction.
                    @endif
                </p>
            </div>
        @else
            <div class="data-grid min-h-0 flex-1 overflow-auto">
                <table class="min-w-full border-separate border-spacing-0">
                    <thead class="sticky top-0 z-10">
                        <tr>
                            <th class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[110px]">Date</th>
                            <th class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Description</th>
                            <th class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-red-500 w-[130px]">Money Out</th>
                            <th class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-green-600 w-[130px]">Money In</th>
                            <th class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[130px]">Balance</th>
                            <th class="border-b border-slate-200 bg-slate-50 px-3 py-3 w-[60px]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            @php
                                $rowSearch = strtolower(implode(' ', array_filter([
                                    $entry->date->format('Y-m-d'),
                                    $entry->date->format('M d, Y'),
                                    $entry->description,
                                    $entry->notes,
                                    $entry->amount_out ? number_format($entry->amount_out, 2) : '',
                                    $entry->amount_in ? number_format($entry->amount_in, 2) : '',
                                    $entry->isTransfer() ? 'transfer' : '',
                                ])));
                            @endphp
                            <tr class="group transition-colors hover:bg-slate-50/70"
                                data-ledger-row
                                data-search="{{ $rowSearch }}"
                                x-show="ledgerVisible(@js($rowSearch))"
                                x-cloak>
                                <td class="border-b border-slate-100 px-4 py-2.5 tabular-nums text-[12.5px] text-slate-600 whitespace-nowrap">
                                    {{ $entry->date->format('M d, Y') }}
                                </td>
                                <td class="border-b border-slate-100 px-4 py-2.5">
                                    <div class="flex items-start gap-1.5">
                                        @if ($entry->isTransfer())
                                            <span class="mt-[3px] inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-blue-50">
                                                <i data-lucide="arrow-left-right" class="h-2.5 w-2.5 text-blue-500"></i>
                                            </span>
                                        @endif
                                        <span class="text-[13px] font-medium text-slate-700 leading-snug">{{ $entry->description }}</span>
                                    </div>
                                    @if ($entry->notes)
                                        <p class="mt-0.5 pl-5 text-[11.5px] text-slate-400">{{ $entry->notes }}</p>
                                    @endif
                                </td>
                                <td class="border-b border-slate-100 px-4 py-2.5 text-right tabular-nums">
                                    @if ($entry->amount_out)
                                        <span class="text-[13px] font-semibold text-red-500">₱{{ number_format($entry->amount_out, 2) }}</span>
                                    @else
                                        <span class="text-slate-200">—</span>
                                    @endif
                                </td>
                                <td class="border-b border-slate-100 px-4 py-2.5 text-right tabular-nums">
                                    @if ($entry->amount_in)
                                        <span class="text-[13px] font-semibold text-green-600">₱{{ number_format($entry->amount_in, 2) }}</span>
                                    @else
                                        <span class="text-slate-200">—</span>
                                    @endif
                                </td>
                                <td class="border-b border-slate-100 px-4 py-2.5 text-right tabular-nums">
                                    <span class="text-[13px] font-semibold {{ $entry->running_balance < 0 ? 'text-red-600' : 'text-omet-navy' }}">
                                        ₱{{ number_format($entry->running_balance, 2) }}
                                    </span>
                                </td>
                                <td class="border-b border-slate-100 px-2 py-2.5">
                                    <div class="flex items-center justify-center gap-0.5">
                                        @if ($entry->isTransfer())
                                            <form method="POST"
                                                  action="{{ route('accounts.transfers.destroy', $entry->transfer_id) }}"
                                                  onsubmit="return confirm('Reverse this transfer? Both legs will be removed.');"
                                                  class="inline-flex">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="rounded p-1 text-slate-300 transition hover:bg-red-50 hover:text-red-500"
                                                        title="Reverse transfer">
                                                    <i data-lucide="undo-2" class="h-3.5 w-3.5"></i>
                                                </button>
                                            </form>
                                        @else
                                            @php
                                                $editPayload = [
                                                    'id'          => $entry->id,
                                                    'date'        => $entry->date->toDateString(),
                                                    'description' => $entry->description,
                                                    'amount_out'  => $entry->amount_out,
                                                    'amount_in'   => $entry->amount_in,
                                                    'notes'       => $entry->notes,
                                                ];
                                                $editActionUrl = route('accounts.entries.update', $entry->id);
                                            @endphp
                                            <button type="button"
                                                    @click="openEdit(@js($editPayload), @js($editActionUrl))"
                                                    class="rounded p-1 text-slate-300 transition hover:bg-slate-100 hover:text-slate-600"
                                                    title="Edit entry">
                                                <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                            </button>
                                            <form method="POST"
                                                  action="{{ route('accounts.entries.destroy', $entry->id) }}"
                                                  onsubmit="return confirm('Delete this entry?');"
                                                  class="inline-flex">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="rounded p-1 text-slate-300 transition hover:bg-red-50 hover:text-red-500"
                                                        title="Delete entry">
                                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="border-t-2 border-slate-200 bg-slate-50 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                {{ ($from || $to) ? 'Period totals' : 'Totals' }}
                                <span class="ml-2 font-normal normal-case text-slate-400">{{ $entries->count() }} {{ Str::plural('entry', $entries->count()) }}</span>
                            </td>
                            <td class="border-t-2 border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[13px] font-semibold tabular-nums text-red-500">
                                ₱{{ number_format($entries->sum('amount_out'), 2) }}
                            </td>
                            <td class="border-t-2 border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[13px] font-semibold tabular-nums text-green-600">
                                ₱{{ number_format($entries->sum('amount_in'), 2) }}
                            </td>
                            <td class="border-t-2 border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[13px] font-semibold tabular-nums text-omet-navy">
                                ₱{{ number_format($activeAccount->currentBalance(), 2) }}
                            </td>
                            <td class="border-t-2 border-slate-200 bg-slate-50"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

@else

    <div class="accounts-hub-overview flex w-full flex-col gap-3 lg:gap-4">

    @php
        $accentColors = [
            'omet'      => '#185FA5',
            'corange'      => '#8B5CF6',
            'personal-mrj' => '#10B981',
            'joint'        => '#F59E0B',
            'dollar'       => '#64748B',
            'kids'         => '#E24B4A',
        ];
        $jointTotal       = (float) ($entities->firstWhere('slug', 'joint')?->computed_total ?? 0);
        $totalAccountCount = (int) $entities->sum('bank_accounts_count');

        $listRows = collect();
        foreach ($entities as $__entity) {
            $__accent = $accentColors[$__entity->slug] ?? '#64748B';
            $__sortedAccounts = $__entity->bankAccounts
                ->sortBy(fn ($a) => ($a->computed_balance ?? 0) == 0 ? 1 : 0)
                ->values();
            foreach ($__sortedAccounts as $__acct) {
                $listRows->push([
                    'account' => $__acct,
                    'entity'  => $__entity,
                    'accent'  => $__accent,
                ]);
            }
        }
    @endphp

    {{-- ── Summary metrics ───────────────────────────────────────────────────── --}}
    <div class="accounts-hub-metrics grid w-full shrink-0 grid-cols-1 sm:grid-cols-3">
        <div class="accounts-hub-metric-card">
            <div class="flex items-start justify-between gap-2">
                <p class="accounts-hub-metric-label">Total cash in bank</p>
                <span class="accounts-hub-metric-chip shrink-0 text-[#185FA5]" aria-hidden="true">
                    <i data-lucide="piggy-bank" class="h-3.5 w-3.5"></i>
                </span>
            </div>
            <p class="accounts-hub-metric-value">₱{{ number_format($totalCashInBank, 0) }}</p>
        </div>
        <div class="accounts-hub-metric-card">
            <div class="flex items-start justify-between gap-2">
                <p class="accounts-hub-metric-label">Active accounts</p>
                <span class="accounts-hub-metric-chip shrink-0 text-[#185FA5]" aria-hidden="true">
                    <i data-lucide="landmark" class="h-3.5 w-3.5"></i>
                </span>
            </div>
            <p class="accounts-hub-metric-value">{{ $totalAccountCount }}</p>
        </div>
        <div class="accounts-hub-metric-card">
            <div class="flex items-start justify-between gap-2">
                <p class="accounts-hub-metric-label">Joint total</p>
                <span class="accounts-hub-metric-chip shrink-0 text-[#185FA5]" aria-hidden="true">
                    <i data-lucide="users" class="h-3.5 w-3.5"></i>
                </span>
            </div>
            <p class="accounts-hub-metric-value {{ $jointTotal < 0 ? 'accounts-hub-metric-value--danger' : '' }}">
                ₱{{ number_format($jointTotal, 2) }}
            </p>
        </div>
    </div>

    <p role="status"
       class="rounded-lg border border-slate-200/90 bg-white px-4 py-8 text-center text-[12px] font-medium leading-relaxed text-slate-500 shadow-sm ring-1 ring-slate-900/[0.03] sm:text-[13px]"
       aria-live="polite"
       x-show="overviewHasSearchQuery && !overviewSearchHasHits"
       x-cloak>
        <span class="sr-only">No accounts match.</span>
        No accounts match<span class="text-slate-800" x-text="'\u00a0\u201c' + overviewAccountSearch.trim() + '\u201d'"></span><span class="font-normal text-slate-400">.</span>

    </p>

    {{-- ── Grid view: 3 cols (desktop) ──────────────────────────────────────────── --}}
    <div x-show="!overviewHasSearchQuery || overviewSearchHasHits" class="accounts-hub-overview-views contents">
    <div class="accounts-hub-entity-grid" x-show="overviewLayout === 'grid'" x-cloak>
        @foreach ($entities as $entity)
        @php
            $accent = $accentColors[$entity->slug] ?? '#64748B';
            $sorted = $entity->bankAccounts
                ->sortBy(fn ($a) => ($a->computed_balance ?? 0) == 0 ? 1 : 0)
                ->values();
        @endphp

        <div class="accounts-hub-group-card h-full"
             style="--hub-accent: {{ $accent }}"
             x-show="overviewEntitySlugVisible(@js($entity->slug))"
             x-cloak>

            {{-- Entity header ───────────────────────────────────────────────────────── --}}
            <div class="accounts-hub-group-header cursor-pointer outline-none transition-[background-color,box-shadow] duration-150 ease-out focus-visible:bg-slate-50"
                 tabindex="0"
                 role="button"
                 aria-label="Focus {{ $entity->name }}"
                 @click="focusEntity('{{ $entity->slug }}')"
                 x-on:keydown.enter.prevent="focusEntity('{{ $entity->slug }}')"
                 x-on:keydown.space.prevent="focusEntity('{{ $entity->slug }}')">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-2.5">
                        <span class="accounts-hub-entity-accent mt-[0.4375rem] h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $accent }}" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <span class="accounts-hub-group-title block truncate">{{ $entity->name }}</span>
                        </div>
                    </div>
                    <span class="accounts-hub-count-pill">
                        {{ $entity->bankAccounts->count() }} {{ Str::plural('account', $entity->bankAccounts->count()) }}
                    </span>
                </div>
                @php $grpTotal = (float) $entity->computed_total; @endphp
                <div class="accounts-hub-group-total-block">
                    <span class="accounts-hub-group-total-label">Total</span>
                    <span @class([
                        'accounts-hub-group-total-value tabular-nums',
                        'text-red-600' => $grpTotal < 0,
                        'accounts-hub-group-total-value--muted' => $grpTotal == 0.0,
                        'text-slate-900' => $grpTotal > 0,
                    ])>
                        ₱{{ number_format($entity->computed_total, 2) }}
                    </span>
                </div>
            </div>

            {{-- Accounts: list-row layout (no table markup) ───────────────────────── --}}
            <div class="accounts-hub-group-body">
                @if ($sorted->isEmpty())
                    <div class="accounts-hub-empty-placeholder">
                        <div class="accounts-hub-empty-icon" aria-hidden="true">
                            <i data-lucide="inbox" class="h-[20px] w-[20px]"></i>
                        </div>
                        <p class="accounts-hub-empty-title">No linked accounts</p>
                        <p class="accounts-hub-empty-desc"><span class="text-slate-600">Nothing to show.</span> Add an account above to track balances.</p>
                    </div>
                @else
                    <div class="accounts-hub-group-scroll" role="presentation">
                        <p class="sr-only">Accounts for {{ $entity->name }}. Press Enter or Space to open.</p>
                        <div class="accounts-hub-account-rows">
                            @foreach ($sorted as $account)
                                @php $bal = (float) ($account->computed_balance ?? 0); @endphp
                                <div
                                    tabindex="0"
                                    role="button"
                                    class="accounts-hub-account-row outline-none transition-[background-color,box-shadow] duration-120 ease-out"
                                    title="Open {{ $account->name }} ledger"
                                    x-show="overviewAccountRowVisible({{ $account->id }})"
                                    x-cloak
                                    @click="pickAccount({{ $account->id }})"
                                    x-on:keydown.enter.prevent="pickAccount({{ $account->id }})"
                                    x-on:keydown.space.prevent="pickAccount({{ $account->id }})">
                                    <span class="accounts-hub-account-row-name">{{ $account->name }}</span>
                                    <span @class([
                                        'accounts-hub-account-row-balance tabular-nums',
                                        'text-red-600' => $bal < 0,
                                        'accounts-hub-num-muted' => $bal === 0.0,
                                        'text-slate-900' => $bal > 0,
                                    ])>
                                        ₱{{ number_format($bal, 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

        </div>
        @endforeach
    </div>

    {{-- ── List view: unified data-grid table ──────────────────────────────── --}}
    <div x-show="overviewLayout === 'list'" x-cloak>
        <div class="data-grid overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th scope="col" class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Account</th>
                        <th scope="col" class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Business</th>
                        <th scope="col" class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Bank</th>
                        <th scope="col" class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Balance</th>
                        <th scope="col" class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[80px]">Status</th>
                        <th scope="col" class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[140px]">Updated</th>
                        <th scope="col" class="w-[40px]"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($listRows as $row)
                        @php
                            $account = $row['account'];
                            $entity = $row['entity'];
                            $accent = $row['accent'];
                            $bal = (float) ($account->computed_balance ?? 0);
                            $bankNm = trim((string) $account->bank_name);
                            $updated = $account->updated_at;
                            $balColor = $bal < 0 ? 'text-red-600' : ($bal === 0.0 ? 'text-slate-400' : 'text-omet-navy');
                        @endphp
                        <tr class="cursor-pointer transition-colors hover:bg-slate-50/70"
                            tabindex="0"
                            role="button"
                            title="Open {{ $account->name }} ledger"
                            @click="pickAccount({{ $account->id }})"
                            x-on:keydown.enter.prevent="pickAccount({{ $account->id }})"
                            x-on:keydown.space.prevent="pickAccount({{ $account->id }})"
                            x-show="overviewAccountRowVisible({{ $account->id }})"
                            x-cloak>
                            <td>
                                <span class="block font-semibold text-slate-800">{{ $account->name }}</span>
                                @if (method_exists($account, 'maskedAccountNumber') && $account->maskedAccountNumber())
                                    <span class="block text-slate-400">{{ $account->maskedAccountNumber() }}</span>
                                @endif
                            </td>
                            <td>
                                <button type="button"
                                        class="inline-flex items-center gap-1.5 text-slate-700 hover:text-omet-navy"
                                        title="Filter by {{ $entity->name }}"
                                        @click.stop="focusEntity('{{ $entity->slug }}')">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $accent }}"></span>
                                    <span class="truncate">{{ $entity->name }}</span>
                                </button>
                            </td>
                            <td>
                                @if ($bankNm !== '')
                                    <span class="uppercase text-slate-700">{{ $bankNm }}</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="text-right font-semibold tabular-nums {{ $balColor }}">
                                ₱{{ number_format($bal, 2) }}
                            </td>
                            <td>
                                @if ($account->is_active)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                                        <span class="h-1 w-1 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                        <span class="h-1 w-1 rounded-full bg-slate-400"></span>
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="text-slate-500">
                                @if ($updated)
                                    <time datetime="{{ $updated->toIso8601String() }}"
                                          title="{{ $updated->timezone(config('app.timezone'))->format('M j, Y g:i A') }}">
                                        {{ $updated->timezone(config('app.timezone'))->diffForHumans() }}
                                    </time>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <button type="button"
                                        class="rounded p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                        title="Open ledger"
                                        aria-label="Open {{ $account->name }}"
                                        @click.stop="pickAccount({{ $account->id }})">
                                    <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-14 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <i data-lucide="layers" class="h-7 w-7 text-slate-300"></i>
                                    <p class="text-sm font-semibold text-slate-500">No accounts yet</p>
                                    <p class="max-w-sm text-[11.5px] text-slate-400">Link a bank account to see balances here and reconcile activity in ledgers.</p>
                                    <button type="button"
                                            class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-omet-blue px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-omet-lightblue"
                                            @click="openAddAccount()">
                                        <i data-lucide="plus" class="h-3.5 w-3.5 shrink-0"></i>
                                        Add account
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{-- /.accounts-hub-overview-views --}}
    </div>

    </div>{{-- /.accounts-hub-overview --}}

@endisset

</div>{{-- max-width content --}}

{{-- ══════════════════════════════════════════════════════════════════════════
     MODALS
     ══════════════════════════════════════════════════════════════════════ --}}

{{-- ── Add Entry ─────────────────────────────────────────────────────────── --}}
@isset($activeAccount)
<div x-show="showEntry" style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
     x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-100 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     @keydown.escape.window="showEntry = false">
    <div @click.outside="showEntry = false"
         class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
         x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
            <div>
                <h4 class="text-base font-bold text-omet-navy">Add Ledger Entry</h4>
                <p class="text-xs text-gray-400">{{ $activeAccount->name }} · {{ $activeAccount->bank_name }}</p>
            </div>
            <button type="button" @click="showEntry = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('accounts.entries.store') }}" class="space-y-4 px-6 py-5">
            @csrf
            <input type="hidden" name="bank_account_id" value="{{ $activeAccount->id }}">

            <div>
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-500">Transaction Type</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="out" x-model="entryType" class="peer sr-only" checked>
                        <div class="flex items-center justify-center gap-1.5 rounded-xl border-2 py-3 text-sm font-bold transition peer-checked:border-red-400 peer-checked:bg-red-50 peer-checked:text-red-600 border-slate-200 text-gray-400">
                            <i data-lucide="arrow-up-right" class="h-4 w-4"></i> Money Out
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="in" x-model="entryType" class="peer sr-only">
                        <div class="flex items-center justify-center gap-1.5 rounded-xl border-2 py-3 text-sm font-bold transition peer-checked:border-green-400 peer-checked:bg-green-50 peer-checked:text-green-600 border-slate-200 text-gray-400">
                            <i data-lucide="arrow-down-left" class="h-4 w-4"></i> Money In
                        </div>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Date</label>
                    <input type="date" name="date" :value="entryDate" required
                        class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue focus:bg-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Amount (₱)</label>
                    <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required
                        class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue focus:bg-white">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Description</label>
                <input type="text" name="description" placeholder="e.g. BGC Payroll, APMC Collection…" required maxlength="255"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue focus:bg-white">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">
                    Notes <span class="font-normal normal-case text-gray-400">(optional)</span>
                </label>
                <textarea name="notes" rows="2" placeholder="Reference number, remarks…" maxlength="500"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-omet-blue focus:bg-white"></textarea>
            </div>

            <div class="flex gap-3 border-t border-slate-100 pt-4">
                <button type="button" @click="showEntry = false"
                    class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 rounded-xl bg-[#185FA5] py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#124a84]">
                    Save Entry
                </button>
            </div>
        </form>
    </div>
</div>
@endisset

{{-- ── Edit Entry ─────────────────────────────────────────────────────────── --}}
<div x-show="showEditEntry" style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
     x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     @keydown.escape.window="showEditEntry = false">
    <div @click.outside="showEditEntry = false"
         class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">

        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
            <h4 class="text-base font-bold text-omet-navy">Edit Ledger Entry</h4>
            <button type="button" @click="showEditEntry = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <form :action="editAction" method="POST" class="space-y-4 px-6 py-5">
            @csrf @method('PUT')

            <div>
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-500">Transaction Type</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="out" x-model="editType" class="peer sr-only">
                        <div class="flex items-center justify-center gap-1.5 rounded-xl border-2 py-3 text-sm font-bold transition peer-checked:border-red-400 peer-checked:bg-red-50 peer-checked:text-red-600 border-slate-200 text-gray-400">
                            <i data-lucide="arrow-up-right" class="h-4 w-4"></i> Money Out
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="in" x-model="editType" class="peer sr-only">
                        <div class="flex items-center justify-center gap-1.5 rounded-xl border-2 py-3 text-sm font-bold transition peer-checked:border-green-400 peer-checked:bg-green-50 peer-checked:text-green-600 border-slate-200 text-gray-400">
                            <i data-lucide="arrow-down-left" class="h-4 w-4"></i> Money In
                        </div>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Date</label>
                    <input type="date" name="date" x-model="editDate" required
                        class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue focus:bg-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Amount (₱)</label>
                    <input type="number" name="amount" min="0.01" step="0.01" x-model="editAmount" required
                        class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue focus:bg-white">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Description</label>
                <input type="text" name="description" x-model="editDescription" required maxlength="255"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue focus:bg-white">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">
                    Notes <span class="font-normal normal-case text-gray-400">(optional)</span>
                </label>
                <textarea name="notes" rows="2" x-model="editNotes" maxlength="500"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-omet-blue focus:bg-white"></textarea>
            </div>

            <div class="flex gap-3 border-t border-slate-100 pt-4">
                <button type="button" @click="showEditEntry = false"
                    class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 rounded-xl bg-[#185FA5] py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#124a84]">
                    Save changes
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Quick Transfer ──────────────────────────────────────────────────────── --}}
@isset($activeAccount)
@php
    $pickerAccounts = $allAccounts->map(fn ($a) => [
        'id'     => $a->id,
        'label'  => ($a->entity?->name ?? '?') . ' — ' . $a->name,
        'search' => strtolower(implode(' ', array_filter([$a->entity?->name, $a->name, $a->bank_name, (string)($a->account_number ?? '')]))),
    ])->values();
    $toDefaultId = $allAccounts->firstWhere('id', '!=', $activeAccount->id)?->id ?? $activeAccount->id;
@endphp
<div x-show="showTransfer" style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
     x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     @keydown.escape.window="showTransfer = false">
    <div @click.outside="showTransfer = false"
         class="w-full max-w-md overflow-visible rounded-2xl border border-slate-200 bg-white shadow-2xl"
         x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
            <div>
                <h4 class="text-base font-bold text-omet-navy">Transfer Between Accounts</h4>
                <p class="text-xs text-gray-400">Both accounts will be updated automatically.</p>
            </div>
            <button type="button" @click="showTransfer = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('accounts.transfers.store') }}" class="space-y-4 overflow-visible px-6 py-5">
            @csrf
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">From Account</label>
                @include('accounts.partials.account-combobox', [
                    'fieldName'          => 'from_account_id',
                    'accountsForPicker'  => $pickerAccounts,
                    'defaultId'          => $activeAccount->id,
                    'buttonClass'        => 'flex h-10 w-full items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 text-left text-sm text-gray-800 outline-none transition hover:border-slate-300',
                ])
            </div>

            <div class="flex items-center gap-3">
                <div class="h-px flex-1 bg-slate-200"></div>
                <div class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white">
                    <i data-lucide="arrow-down" class="h-3.5 w-3.5 text-gray-400"></i>
                </div>
                <div class="h-px flex-1 bg-slate-200"></div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">To Account</label>
                @include('accounts.partials.account-combobox', [
                    'fieldName'          => 'to_account_id',
                    'accountsForPicker'  => $pickerAccounts,
                    'defaultId'          => $toDefaultId,
                    'buttonClass'        => 'flex h-10 w-full items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 text-left text-sm text-gray-800 outline-none transition hover:border-slate-300',
                ])
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Date</label>
                    <input type="date" name="date" value="{{ now()->toDateString() }}" required
                        class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Amount (₱)</label>
                    <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required
                        class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">
                    Memo <span class="font-normal normal-case text-gray-400">(optional)</span>
                </label>
                <input type="text" name="memo" placeholder="Reason for transfer…" maxlength="255"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue">
            </div>

            <p class="rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2 text-[11.5px] text-blue-800">
                For full options (purpose, project tags), use
                <a href="{{ route('transfers.index') }}" class="font-semibold underline-offset-1 hover:underline">Transfers / Intercompany</a>.
            </p>

            <div class="flex gap-3 border-t border-slate-100 pt-4">
                <button type="button" @click="showTransfer = false"
                    class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 rounded-xl bg-[#185FA5] py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#124a84]">
                    Confirm Transfer
                </button>
            </div>
        </form>
    </div>
</div>
@endisset

{{-- ── Add Bank Account ────────────────────────────────────────────────────── --}}
<div x-show="showAddAccount" style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
     x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     @keydown.escape.window="showAddAccount = false">
    <div @click.outside="showAddAccount = false"
         class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
         x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
            <div>
                <h4 class="text-base font-bold text-omet-navy">Add Bank Account</h4>
                <p class="text-xs text-gray-400">The new account will open in the ledger view.</p>
            </div>
            <button type="button" @click="showAddAccount = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('accounts.bank-accounts.store') }}" class="space-y-4 px-6 py-5">
            @csrf
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Business category</label>
                <select name="entity_id" required x-ref="addAccountEntity"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue">
                    @foreach ($entities as $ent)
                        <option value="{{ $ent->id }}">{{ $ent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Account Name</label>
                <input type="text" name="name" placeholder="e.g. BDO Savings" required maxlength="255"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Bank Name</label>
                <input type="text" name="bank_name" placeholder="e.g. BDO Unibank" required maxlength="255"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">
                        Account # <span class="font-normal normal-case text-gray-400">(optional)</span>
                    </label>
                    <input type="text" name="account_number" maxlength="100"
                        class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Opening Balance (₱)</label>
                    <input type="number" name="opening_balance" value="0" step="0.01"
                        class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue">
                </div>
            </div>
            <div class="flex gap-3 border-t border-slate-100 pt-4">
                <button type="button" @click="showAddAccount = false"
                    class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 rounded-xl bg-[#185FA5] py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#124a84]">
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Edit Account ────────────────────────────────────────────────────────── --}}
@isset($activeAccount)
<div x-show="showEditAccount" style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
     x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     @keydown.escape.window="showEditAccount = false">
    <div @click.outside="showEditAccount = false"
         class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">

        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
            <div>
                <h4 class="text-base font-bold text-omet-navy">Edit Account</h4>
                <p class="text-xs text-gray-400">{{ $activeAccount->name }} · {{ $activeAccount->entity?->name }}</p>
            </div>
            <button type="button" @click="showEditAccount = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('accounts.bank-accounts.update', $activeAccount->id) }}" class="space-y-4 px-6 py-5">
            @csrf @method('PUT')

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Account Name</label>
                <input type="text" name="name" required maxlength="255"
                    value="{{ old('name', $activeAccount->name) }}"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Bank Name</label>
                <input type="text" name="bank_name" required maxlength="255"
                    value="{{ old('bank_name', $activeAccount->bank_name) }}"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">
                        Account # <span class="font-normal normal-case text-gray-400">(optional)</span>
                    </label>
                    <input type="text" name="account_number" maxlength="100"
                        value="{{ old('account_number', $activeAccount->account_number) }}"
                        class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Opening Balance (₱)</label>
                    <input type="number" name="opening_balance" step="0.01"
                        value="{{ old('opening_balance', $activeAccount->opening_balance) }}"
                        class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-gray-800 outline-none focus:border-omet-blue">
                </div>
            </div>

            <p class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-[11px] text-amber-700">
                <i data-lucide="info" class="mr-1 inline h-3 w-3"></i>
                Changing the opening balance recomputes all running balances for this account.
            </p>

            <div class="flex gap-3 border-t border-slate-100 pt-4">
                <button type="button" @click="showEditAccount = false"
                    class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 rounded-xl bg-[#185FA5] py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#124a84]">
                    Save Account
                </button>
            </div>
        </form>
    </div>
</div>
@endisset

</div>{{-- end x-data --}}
</x-app-layout>
