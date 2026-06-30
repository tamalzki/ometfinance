<x-app-layout page-title="Transfers / Intercompany">
@php
    /* --- Build a single, fast-search-friendly picker list of bank accounts --- */
    $accountsForPicker = $allAccounts->map(fn ($a) => [
        'id'     => $a->id,
        'label'  => $a->entity?->name . ' — ' . $a->name,
        'bank'   => $a->bank_name,
        'entity' => $a->entity?->name ?? '',
        'search' => strtolower(implode(' ', array_filter([
            $a->entity?->name, $a->name, $a->bank_name, (string) ($a->account_number ?? ''),
        ]))),
    ])->values();

    /* --- Same shape for projects (used by the optional from/to project tags) --- */
    $projectsForPicker = $projects->map(fn ($p) => [
        'id'     => $p->id,
        'label'  => $p->name . ($p->code ? ' (' . $p->code . ')' : ''),
        'kind'   => $p->kind === 'in_house' ? 'In-house' : 'External',
        'client' => $p->client_name ?: '',
        'search' => strtolower(implode(' ', array_filter([
            $p->name, $p->code, $p->client_name, $p->kind === 'in_house' ? 'in-house' : 'external',
        ]))),
    ])->values();

    $firstAccount  = $allAccounts->first();
    $secondAccount = $allAccounts->firstWhere('id', '!=', $firstAccount?->id);

    $purposeOptions = \App\Models\Transfer::PURPOSES;
@endphp

<script>
document.addEventListener('alpine:init', () => {
    const searchMixin = typeof window.disburseListSearchMixin === 'function'
        ? window.disburseListSearchMixin()
        : (typeof window.disburseListSearchFallback === 'function' ? window.disburseListSearchFallback() : {});

    Alpine.data('transfersPage', () => ({
        ...searchMixin,
        showForm: @json($errors->any()),
        editId: @json(old('editing_transfer_id') ? (int) old('editing_transfer_id') : null),
        accounts: @json($accountsForPicker),
        projects: @json($projectsForPicker),
        purposes: @json($purposeOptions),
        fromAccountId: @json((string) old('from_account_id', (string) ($firstAccount?->id ?? ''))),
        toAccountId: @json((string) old('to_account_id', (string) ($secondAccount?->id ?? $firstAccount?->id ?? ''))),
        fromProjectId: @json((string) old('from_project_id', '')),
        toProjectId: @json((string) old('to_project_id', '')),
        purpose: @json((string) old('purpose', 'intercompany')),
        formDate: @json((string) old('date', now()->format('Y-m-d'))),
        formAmount: @json((string) old('amount', '')),
        formMemo: @json(old('memo', '')),
        formReason: @json(old('reason', '')),

        fromOpen: false,
        fromQuery: '',
        toOpen: false,
        toQuery: '',
        fromProjOpen: false,
        fromProjQuery: '',
        toProjOpen: false,
        toProjQuery: '',

        openAdd() {
            this.editId = null;
            this.fromAccountId = @json((string) ($firstAccount?->id ?? ''));
            this.toAccountId = @json((string) ($secondAccount?->id ?? $firstAccount?->id ?? ''));
            this.fromProjectId = '';
            this.toProjectId = '';
            this.purpose = 'intercompany';
            this.formDate = @json(now()->format('Y-m-d'));
            this.formAmount = '';
            this.formMemo = '';
            this.formReason = '';
            this.showForm = true;
        },
        openEdit(t) {
            this.editId = t.id;
            this.fromAccountId = String(t.from_account_id);
            this.toAccountId = String(t.to_account_id);
            this.fromProjectId = t.from_project_id ? String(t.from_project_id) : '';
            this.toProjectId = t.to_project_id ? String(t.to_project_id) : '';
            this.purpose = t.purpose || 'intercompany';
            this.formDate = t.date;
            this.formAmount = String(t.amount);
            this.formMemo = t.memo || '';
            this.formReason = t.reason || '';
            this.showForm = true;
        },
        closeForm() {
            this.showForm = false;
            this.editId = null;
        },

        accountLabel(id) {
            const a = this.accounts.find(x => String(x.id) === String(id));
            return a ? a.label : '— pick account —';
        },
        projectLabel(id) {
            if (! id) { return '— optional: tag a project —'; }
            const p = this.projects.find(x => String(x.id) === String(id));
            return p ? p.label : '—';
        },
        filteredAccounts(q) {
            const needle = (q || '').trim().toLowerCase();
            if (! needle) { return this.accounts; }
            return this.accounts.filter(a => a.search.includes(needle));
        },
        filteredProjects(q) {
            const needle = (q || '').trim().toLowerCase();
            if (! needle) { return this.projects; }
            return this.projects.filter(p => p.search.includes(needle));
        },
    }));
});
</script>

<div
    x-data="transfersPage"
    class="disburse-page"
>

{{-- ── Flash ───────────────────────────────────────────────────────────── --}}
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
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ── Page header ───────────────────────────────────────────────────────── --}}
<div class="disburse-page-header">
    <div class="min-w-0">
        <h1 class="text-xl font-bold tracking-tight text-omet-navy">Transfers</h1>
        <p class="text-xs text-slate-500"><span data-disburse-result-count>{{ $summary['count'] }}</span> @if ($search)<span data-disburse-result-mode>matching</span>@else{{ \Illuminate\Support\Str::plural('transfer', $summary['count']) }}@endif · ₱{{ number_format($summary['total'], 2) }} total moved</p>
    </div>
    <button type="button"
            @click="openAdd()"
            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-omet-blue px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-omet-lightblue sm:w-auto">
        <i data-lucide="plus" class="h-4 w-4"></i>
        Add Transfer
    </button>
</div>

{{-- ── Summary cards (financial KPI style, matches projects index) ────────── --}}
<div class="disburse-kpi-grid">
    {{-- Total moved --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Total moved</p>
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-emerald-50">
                <i data-lucide="wallet" class="h-3.5 w-3.5 text-emerald-600"></i>
            </span>
        </div>
        <p class="mt-2 text-lg font-bold tabular-nums text-omet-navy">₱{{ number_format($summary['total'], 2) }}</p>
        <p class="mt-0.5 text-[11px] text-slate-500">across {{ $summary['count'] }} {{ \Illuminate\Support\Str::plural('transfer', $summary['count']) }}</p>
    </div>

    {{-- Shown --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">In view</p>
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-omet-blue/5">
                <i data-lucide="list-ordered" class="h-3.5 w-3.5 text-omet-blue"></i>
            </span>
        </div>
        <p class="mt-2 text-lg font-bold tabular-nums text-omet-navy">{{ $summary['count'] }}</p>
        <p class="mt-0.5 text-[11px] text-slate-500">{{ ($from || $to || $search) ? 'matches current filters' : 'all transfers' }}</p>
    </div>

    {{-- Intercompany --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Intercompany</p>
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-amber-50">
                <i data-lucide="building-2" class="h-3.5 w-3.5 text-amber-700"></i>
            </span>
        </div>
        <p class="mt-2 text-lg font-bold tabular-nums text-omet-navy">{{ $summary['intercompany'] }}</p>
        <p class="mt-0.5 text-[11px] text-slate-500">between entities</p>
    </div>

    {{-- Project-tagged --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Project-tagged</p>
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-rose-50">
                <i data-lucide="folder-kanban" class="h-3.5 w-3.5 text-rose-600"></i>
            </span>
        </div>
        <p class="mt-2 text-lg font-bold tabular-nums text-omet-navy">{{ $summary['project_linked'] }}</p>
        <p class="mt-0.5 text-[11px] text-slate-500">linked to a project</p>
    </div>
</div>

{{-- ── Toolbar (flat, unified with accounts/projects) ─────────────────────── --}}
@php $hasDateFilter = (bool) ($from || $to); @endphp
<div class="disburse-toolbar">
    <form method="GET" action="{{ route('transfers.index') }}" class="disburse-filter-form w-full">
        <div class="disburse-search relative min-w-[12rem] flex-1 sm:max-w-xs">
            <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
            <input type="search" name="q" value="{{ $search }}" autocomplete="off"
                   placeholder="Search transfers"
                   aria-label="Search transfers"
                   @input="onSearchInput($event)"
                   @keydown="onSearchKeydown($event)"
                   class="h-9 w-full rounded-md border border-slate-200 bg-white pl-8 pr-7 text-[12.5px] text-slate-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
            @if ($search)
                <a href="{{ route('transfers.index', array_filter(['from' => $from, 'to' => $to])) }}"
                   class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                   aria-label="Clear search">
                    <i data-lucide="x" class="h-3 w-3"></i>
                </a>
            @endif
        </div>

        <label class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-slate-400">
            <i data-lucide="calendar-range" class="h-3.5 w-3.5"></i>
            Period
        </label>
        <input type="date" name="from" value="{{ $from }}" onchange="this.form.submit()"
               class="h-9 min-w-[8.5rem] rounded-lg border border-slate-200 bg-white px-3 text-[12px] text-slate-700 shadow-sm outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
        <span class="text-[11px] text-slate-300">→</span>
        <input type="date" name="to" value="{{ $to }}" onchange="this.form.submit()"
               class="h-9 min-w-[8.5rem] rounded-lg border border-slate-200 bg-white px-3 text-[12px] text-slate-700 shadow-sm outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
        @if ($hasDateFilter || $search)
            <a href="{{ route('transfers.index') }}"
               class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
               title="Clear filters">
                <i data-lucide="x" class="h-3 w-3"></i>
                Clear
            </a>
        @endif
    </form>
</div>

{{-- ── Movements table (unified data-grid) ────────────────────────────────── --}}
@include('transfers.partials.index-table')

{{-- ── NEW / EDIT TRANSFER modal ─────────────────────────────────────────── --}}
<div x-cloak x-show="showForm"
     class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-6"
     @keydown.escape.window="closeForm()">
    <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl"
         @click.outside="closeForm()">
        <form method="POST" class="flex max-h-[90vh] flex-col"
              x-bind:action="editId ? '{{ url('/transfers') }}/' + editId : '{{ route('transfers.store') }}'">
            @csrf
            <template x-if="editId">
                <div>
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="editing_transfer_id" :value="editId">
                </div>
            </template>
            {{-- Header --}}
            <div class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <p class="text-[10.5px] font-semibold uppercase tracking-wider text-omet-blue">Money movement</p>
                    <h2 class="mt-0.5 text-lg font-semibold text-omet-navy" x-text="editId ? 'Edit transfer' : 'Record transfer'"></h2>
                    <p class="mt-1 text-[12px] text-gray-500">Optional project tags post matching inflow/outflow on project books.</p>
                </div>
                <button type="button" @click="closeForm()"
                        class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

                {{-- ── Source side ──────────────────────────────────────── --}}
                <fieldset class="rounded-xl border border-rose-100 bg-rose-50/30 p-4">
                    <legend class="px-2 text-[11px] font-semibold uppercase tracking-wider text-rose-700">
                        Money is leaving from
                    </legend>

                    {{-- From account: searchable dropdown --}}
                    <div class="relative" @click.outside="fromOpen = false">
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">From bank account *</label>
                        <button type="button" @click="fromOpen = !fromOpen; if(fromOpen) $nextTick(() => $refs.fromSearch.focus())"
                                class="flex h-10 w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 text-left text-[13px] text-gray-800 outline-none transition hover:border-slate-300 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                            <span x-text="accountLabel(fromAccountId)"></span>
                            <i data-lucide="chevron-down" class="h-4 w-4 text-gray-400"></i>
                        </button>
                        <input type="hidden" name="from_account_id" :value="fromAccountId">

                        <div x-show="fromOpen" x-cloak
                             class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                            <div class="border-b border-slate-100 p-2">
                                <input x-ref="fromSearch" x-model="fromQuery" type="text"
                                       placeholder="Search bank or entity…"
                                       class="h-8 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[12px] outline-none focus:border-omet-blue focus:bg-white">
                            </div>
                            <div class="max-h-56 overflow-y-auto py-1">
                                <template x-for="a in filteredAccounts(fromQuery)" :key="a.id">
                                    <button type="button"
                                            @click="fromAccountId = a.id; fromOpen = false; fromQuery = ''"
                                            class="flex w-full items-start justify-between gap-2 px-3 py-2 text-left text-[12px] hover:bg-blue-50"
                                            :class="String(a.id) === String(fromAccountId) ? 'bg-blue-50/60 text-omet-blue font-medium' : 'text-gray-700'">
                                        <span>
                                            <span class="block font-medium" x-text="a.label"></span>
                                            <span class="block text-[10px] text-gray-400" x-text="a.bank"></span>
                                        </span>
                                        <i data-lucide="check" class="h-3.5 w-3.5 mt-0.5 text-omet-blue" x-show="String(a.id) === String(fromAccountId)"></i>
                                    </button>
                                </template>
                                <p x-show="filteredAccounts(fromQuery).length === 0"
                                   class="px-3 py-3 text-center text-[11px] text-gray-400">No accounts match.</p>
                            </div>
                        </div>
                    </div>

                    {{-- From project: searchable dropdown --}}
                    <div class="relative mt-3" @click.outside="fromProjOpen = false">
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">
                            Source project <span class="font-normal text-gray-400">(optional · records an outflow on this project)</span>
                        </label>
                        <div class="flex items-stretch gap-1">
                            <button type="button" @click="fromProjOpen = !fromProjOpen; if(fromProjOpen) $nextTick(() => $refs.fromProjSearch.focus())"
                                    class="flex h-10 flex-1 items-center justify-between rounded-lg border border-slate-200 bg-white px-3 text-left text-[12.5px] text-gray-700 outline-none transition hover:border-slate-300 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                                <span x-text="projectLabel(fromProjectId)"></span>
                                <i data-lucide="chevron-down" class="h-4 w-4 text-gray-400"></i>
                            </button>
                            <button type="button" x-show="fromProjectId"
                                    @click.stop="fromProjectId = ''"
                                    class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-[11px] text-gray-500 transition hover:bg-rose-50 hover:text-rose-600">
                                Clear
                            </button>
                        </div>
                        <input type="hidden" name="from_project_id" :value="fromProjectId">

                        <div x-show="fromProjOpen" x-cloak
                             class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                            <div class="border-b border-slate-100 p-2">
                                <input x-ref="fromProjSearch" x-model="fromProjQuery" type="text"
                                       placeholder="Search project name or client…"
                                       class="h-8 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[12px] outline-none focus:border-omet-blue focus:bg-white">
                            </div>
                            <div class="max-h-56 overflow-y-auto py-1">
                                <template x-for="p in filteredProjects(fromProjQuery)" :key="p.id">
                                    <button type="button"
                                            @click="fromProjectId = p.id; fromProjOpen = false; fromProjQuery = ''"
                                            class="flex w-full items-start justify-between gap-2 px-3 py-2 text-left text-[12px] hover:bg-blue-50">
                                        <span>
                                            <span class="block font-medium text-gray-700" x-text="p.label"></span>
                                            <span class="block text-[10px] text-gray-400">
                                                <span x-text="p.kind"></span><template x-if="p.client"><span> · <span x-text="p.client"></span></span></template>
                                            </span>
                                        </span>
                                    </button>
                                </template>
                                <p x-show="filteredProjects(fromProjQuery).length === 0"
                                   class="px-3 py-3 text-center text-[11px] text-gray-400">No projects match.</p>
                            </div>
                        </div>
                    </div>
                </fieldset>

                {{-- ── Destination side ─────────────────────────────────── --}}
                <fieldset class="rounded-xl border border-emerald-100 bg-emerald-50/30 p-4">
                    <legend class="px-2 text-[11px] font-semibold uppercase tracking-wider text-emerald-700">
                        Money is going to
                    </legend>

                    {{-- To account --}}
                    <div class="relative" @click.outside="toOpen = false">
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">To bank account *</label>
                        <button type="button" @click="toOpen = !toOpen; if(toOpen) $nextTick(() => $refs.toSearch.focus())"
                                class="flex h-10 w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 text-left text-[13px] text-gray-800 outline-none transition hover:border-slate-300 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                            <span x-text="accountLabel(toAccountId)"></span>
                            <i data-lucide="chevron-down" class="h-4 w-4 text-gray-400"></i>
                        </button>
                        <input type="hidden" name="to_account_id" :value="toAccountId">

                        <div x-show="toOpen" x-cloak
                             class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                            <div class="border-b border-slate-100 p-2">
                                <input x-ref="toSearch" x-model="toQuery" type="text"
                                       placeholder="Search bank or entity…"
                                       class="h-8 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[12px] outline-none focus:border-omet-blue focus:bg-white">
                            </div>
                            <div class="max-h-56 overflow-y-auto py-1">
                                <template x-for="a in filteredAccounts(toQuery)" :key="a.id">
                                    <button type="button"
                                            @click="toAccountId = a.id; toOpen = false; toQuery = ''"
                                            :disabled="String(a.id) === String(fromAccountId)"
                                            class="flex w-full items-start justify-between gap-2 px-3 py-2 text-left text-[12px] hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40"
                                            :class="String(a.id) === String(toAccountId) ? 'bg-blue-50/60 text-omet-blue font-medium' : 'text-gray-700'">
                                        <span>
                                            <span class="block font-medium" x-text="a.label"></span>
                                            <span class="block text-[10px] text-gray-400" x-text="a.bank"></span>
                                        </span>
                                        <i data-lucide="check" class="h-3.5 w-3.5 mt-0.5 text-omet-blue" x-show="String(a.id) === String(toAccountId)"></i>
                                    </button>
                                </template>
                                <p x-show="filteredAccounts(toQuery).length === 0"
                                   class="px-3 py-3 text-center text-[11px] text-gray-400">No accounts match.</p>
                            </div>
                        </div>
                    </div>

                    {{-- To project --}}
                    <div class="relative mt-3" @click.outside="toProjOpen = false">
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">
                            Destination project <span class="font-normal text-gray-400">(optional · records an inflow on this project)</span>
                        </label>
                        <div class="flex items-stretch gap-1">
                            <button type="button" @click="toProjOpen = !toProjOpen; if(toProjOpen) $nextTick(() => $refs.toProjSearch.focus())"
                                    class="flex h-10 flex-1 items-center justify-between rounded-lg border border-slate-200 bg-white px-3 text-left text-[12.5px] text-gray-700 outline-none transition hover:border-slate-300 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                                <span x-text="projectLabel(toProjectId)"></span>
                                <i data-lucide="chevron-down" class="h-4 w-4 text-gray-400"></i>
                            </button>
                            <button type="button" x-show="toProjectId"
                                    @click.stop="toProjectId = ''"
                                    class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-[11px] text-gray-500 transition hover:bg-rose-50 hover:text-rose-600">
                                Clear
                            </button>
                        </div>
                        <input type="hidden" name="to_project_id" :value="toProjectId">

                        <div x-show="toProjOpen" x-cloak
                             class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                            <div class="border-b border-slate-100 p-2">
                                <input x-ref="toProjSearch" x-model="toProjQuery" type="text"
                                       placeholder="Search project name or client…"
                                       class="h-8 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[12px] outline-none focus:border-omet-blue focus:bg-white">
                            </div>
                            <div class="max-h-56 overflow-y-auto py-1">
                                <template x-for="p in filteredProjects(toProjQuery)" :key="p.id">
                                    <button type="button"
                                            @click="toProjectId = p.id; toProjOpen = false; toProjQuery = ''"
                                            class="flex w-full items-start justify-between gap-2 px-3 py-2 text-left text-[12px] hover:bg-blue-50">
                                        <span>
                                            <span class="block font-medium text-gray-700" x-text="p.label"></span>
                                            <span class="block text-[10px] text-gray-400">
                                                <span x-text="p.kind"></span><template x-if="p.client"><span> · <span x-text="p.client"></span></span></template>
                                            </span>
                                        </span>
                                    </button>
                                </template>
                                <p x-show="filteredProjects(toProjQuery).length === 0"
                                   class="px-3 py-3 text-center text-[11px] text-gray-400">No projects match.</p>
                            </div>
                        </div>
                    </div>
                </fieldset>

                {{-- ── Details ──────────────────────────────────────────── --}}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Date *</label>
                        <input type="date" name="date" required x-model="formDate"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Amount (PHP) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required x-model="formAmount"
                               placeholder="0.00"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] tabular-nums text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-600">Purpose *</label>
                    <select name="purpose" x-model="purpose"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                        @foreach ($purposeOptions as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-600">Reason / context</label>
                    <textarea name="reason" rows="2" x-model="formReason"
                              placeholder="e.g. Funding direct cost payment for Croc Park electrical works"
                              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-[12.5px] text-gray-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10"></textarea>
                </div>

                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-600">Short memo <span class="text-gray-400">(appears on the bank ledger)</span></label>
                    <input type="text" name="memo" maxlength="255" x-model="formMemo" placeholder="e.g. Croc Park direct cost top-up"
                           class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                </div>

                {{-- Live impact summary --}}
                <div class="rounded-lg border border-blue-100 bg-blue-50/60 p-3 text-[12px] text-blue-900">
                    <p class="font-semibold">What this transfer will do</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5 text-blue-800">
                        <li>Decrease balance of <strong x-text="accountLabel(fromAccountId)"></strong>.</li>
                        <li>Increase balance of <strong x-text="accountLabel(toAccountId)"></strong>.</li>
                        <li x-show="fromProjectId">Record an <strong>outflow</strong> on <strong x-text="projectLabel(fromProjectId)"></strong>.</li>
                        <li x-show="toProjectId">Record an <strong>inflow</strong> on <strong x-text="projectLabel(toProjectId)"></strong>.</li>
                    </ul>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50/50 px-6 py-3">
                <button type="button" @click="closeForm()"
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-[12.5px] font-semibold text-gray-600 transition hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-omet-blue px-5 py-2 text-[12.5px] font-semibold text-white shadow-sm transition hover:bg-omet-lightblue">
                    <i data-lucide="check" class="h-3.5 w-3.5"></i>
                    <span x-text="editId ? 'Save changes' : 'Record transfer'"></span>
                </button>
            </div>
        </form>
    </div>
</div>

</div>
</x-app-layout>
