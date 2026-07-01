@props([
    'project',
    'bankAccounts' => collect(),
    'collectionsChrono' => collect(),
    'otherProjects' => collect(),
])

@php
    $statusLabels = [
        'planning'    => 'Planning',
        'active'      => 'Active',
        'in_progress' => 'In progress',
        'on-hold'     => 'On hold',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
    ];
    $badgeClasses = [
        'planning'    => 'bg-slate-100 text-slate-700',
        'active'      => 'bg-blue-100 text-blue-800',
        'in_progress' => 'bg-indigo-100 text-indigo-800',
        'on-hold'     => 'bg-amber-100 text-amber-900',
        'completed'   => 'bg-green-100 text-green-800',
        'cancelled'   => 'bg-red-100 text-red-800',
    ];
    $statusLabel = $statusLabels[$project->status] ?? ucfirst($project->status);
    $statusBadge = $badgeClasses[$project->status] ?? 'bg-gray-100 text-gray-700';

    $totalCollected  = $project->totalCollected();
    $totalExpenses   = $project->totalExpenses();
    $netPosition     = $project->netCashPosition();
    $clientCollected = $project->totalClientCollected();
    $borrowedTotal   = $project->totalBorrowed();
    $totalDeductions = $project->totalDeductions();

    $isExternal     = $project->isExternal();

    // External progress counts client collections only — borrowed funds are
    // not payments against the contract.
    $completionPct  = $project->contract_value > 0
        ? min(100, round(($isExternal ? $clientCollected : $totalCollected) / $project->contract_value * 100, 1))
        : 0;
    $contractLabel  = $isExternal ? 'Contract' : 'Budget';
    $percentLabel   = $isExternal ? '% Collected' : '% Used';
    $contractValue  = (float) $project->contract_value;
    $budgetUsedPct  = $contractValue > 0 ? min(100, round($totalExpenses / $contractValue * 100, 1)) : 0;

    // In-house projects are funded from other accounts: "Inflow" reads as
    // "Funding" everywhere.
    $inflowLabel       = $isExternal ? 'Inflow' : 'Funding';
    $totalInflowLabel  = $isExternal ? 'Total inflow' : 'Total funded';

    // Which pane the inflow modal opens on; restored after validation errors.
    $initialInflowMode = in_array(old('_form'), ['collection', 'funding'], true)
        ? old('_form')
        : ($isExternal ? 'collection' : 'funding');

    // Option lists for the searchable dropdowns in the inflow modal.
    $bankAccountOptions = $bankAccounts->map(fn ($ba) => [
        'value'  => $ba->id,
        'label'  => $ba->name . ' (' . ($ba->entity->name ?? '?') . ')',
        'search' => strtolower($ba->name . ' ' . ($ba->entity->name ?? '') . ' ' . ($ba->bank_name ?? '')),
    ])->values()->all();

    $sourceProjectOptions = $otherProjects->map(fn ($op) => [
        'value'  => $op->id,
        'label'  => $op->name,
        'group'  => $op->kind === 'in_house' ? 'In-house projects' : 'External projects',
        'search' => strtolower($op->name),
    ])->values()->all();

    $navLinks = $isExternal
        ? [
            ['label' => 'Overview',   'icon' => 'layout-dashboard', 'route' => 'projects.show.overview'],
            ['label' => 'Allocation', 'icon' => 'bar-chart-2',      'route' => 'projects.show.allocation'],
            ['label' => 'Inflow',     'icon' => 'arrow-down-circle','route' => 'projects.show.inflow'],
            ['label' => 'Outflow',    'icon' => 'arrow-up-circle',  'route' => 'projects.show.outflow'],
            ['label' => 'History',    'icon' => 'history',          'route' => 'projects.show.history'],
        ]
        : [
            ['label' => 'Overview', 'icon' => 'layout-dashboard',  'route' => 'projects.show.overview'],
            ['label' => 'Summary',  'icon' => 'bar-chart-2',       'route' => 'projects.show.summary'],
            ['label' => 'Funding',  'icon' => 'banknote',          'route' => 'projects.show.inflow'],
            ['label' => 'Outflow',  'icon' => 'arrow-up-circle',   'route' => 'projects.show.outflow'],
            ['label' => 'Ledger',   'icon' => 'book-open',         'route' => 'projects.show.history'],
        ];
@endphp

<div x-data="{
    showNewCollection: {{ old('_form') ? 'true' : 'false' }},
    inflowMode: '{{ $initialInflowMode }}',
    showEdit: {{ session('openEdit') ? 'true' : 'false' }}
}" class="space-y-3">

    {{-- Flash --}}
    @if (session('success'))
    <div class="rounded-md border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any() && ! old('_form'))
    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
        <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    {{-- Sticky header + action bar (back link folded in to save a row) --}}
    <div class="sticky top-0 z-30 -mx-4 sm:-mx-6 lg:-mx-7 border-b border-gray-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur sm:px-6 lg:px-7">
        <a href="{{ route($isExternal ? 'projects.external' : 'projects.in_house') }}"
           class="mb-2 inline-flex w-fit shrink-0 items-center gap-1 text-[11px] font-medium text-slate-400 transition hover:text-omet-navy">
            <i data-lucide="arrow-left" class="h-3 w-3"></i>
            {{ $isExternal ? 'External Projects' : 'In-house Projects' }}
        </a>
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1.5">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <h1 class="truncate text-lg font-bold text-omet-navy">{{ $project->name }}</h1>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusBadge }}">{{ $statusLabel }}</span>
                    @if ($isExternal && $project->client_name)
                    <span class="text-[12px] text-slate-500">· {{ $project->client_name }}</span>
                    @elseif (! $isExternal && $project->location)
                    <span class="text-[12px] text-slate-500">· {{ $project->location }}</span>
                    @endif
                    @if ($project->due_date)
                    <span class="ml-1 inline-flex items-center gap-1 text-[11px] font-medium text-amber-700 tabular-nums">
                        <i data-lucide="flag" class="h-3 w-3"></i>
                        Due {{ $project->due_date->format('M j, Y') }}
                    </span>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                @can('manage-financials')
                <button type="button" @click="showNewCollection = true"
                    class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow ring-1 ring-emerald-700/20 hover:bg-emerald-700">
                    <i data-lucide="plus-circle" class="h-3.5 w-3.5"></i> {{ $inflowLabel }}
                </button>
                @endcan
                <a href="{{ route('vouchers.create', ['project_id' => $project->id]) }}"
                    class="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-3 py-1.5 text-xs font-bold text-white shadow ring-1 ring-red-700/20 hover:bg-red-700">
                    <i data-lucide="minus-circle" class="h-3.5 w-3.5"></i> Outflow
                </a>
                <button type="button" @click="showEdit = true"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:border-omet-blue hover:text-omet-blue">
                    <i data-lucide="pencil" class="h-3 w-3"></i> Edit
                </button>
                <a href="{{ route('projects.exportWorkbook', ['project' => $project]) }}"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:border-omet-blue hover:text-omet-blue">
                    <i data-lucide="file-spreadsheet" class="h-3.5 w-3.5"></i> Export
                </a>
            </div>
        </div>
    </div>

    {{-- KPI strip --}}
    <div class="grid grid-cols-2 gap-2 lg:grid-cols-5">
        <div class="rounded-lg border border-gray-100 bg-white px-3 py-2 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $contractLabel }}</p>
            <p class="mt-0.5 text-sm font-bold tabular-nums text-omet-navy">
                @if ($contractValue > 0) ₱{{ number_format($contractValue, 2) }} @else — @endif
            </p>
        </div>
        <div class="rounded-lg border border-gray-100 bg-white px-3 py-2 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $totalInflowLabel }}</p>
            <p class="mt-0.5 text-sm font-bold tabular-nums text-green-700">₱{{ number_format($totalCollected, 2) }}</p>
            @if ($isExternal && $borrowedTotal > 0)
            <p class="mt-0.5 text-[10px] tabular-nums text-slate-400" title="Collections vs borrowed / project support">
                ₱{{ number_format($clientCollected, 2) }} collected · ₱{{ number_format($borrowedTotal, 2) }} borrowed
            </p>
            @endif
        </div>
        <div class="rounded-lg border border-gray-100 bg-white px-3 py-2 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Total outflow</p>
            <p class="mt-0.5 text-sm font-bold tabular-nums text-red-600">₱{{ number_format($totalExpenses, 2) }}</p>
        </div>
        <div class="rounded-lg border border-gray-100 bg-white px-3 py-2 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Net cash</p>
            <p class="mt-0.5 text-sm font-bold tabular-nums {{ $netPosition >= 0 ? 'text-omet-navy' : 'text-red-600' }}">₱{{ number_format($netPosition, 2) }}</p>
            @if ($totalDeductions > 0)
            <p class="mt-0.5 text-[10px] tabular-nums text-slate-400" title="Collections counted net of VAT/WHT/retention/recoupment/other deductions">
                net of ₱{{ number_format($totalDeductions, 2) }} deducted
            </p>
            @endif
        </div>
        <div class="rounded-lg border border-gray-100 bg-white px-3 py-2 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $percentLabel }}</p>
            <p class="mt-0.5 text-sm font-bold tabular-nums text-indigo-600">
                @if ($contractValue > 0)
                    {{ $isExternal ? $completionPct : $budgetUsedPct }}%
                @else
                    —
                @endif
            </p>
        </div>
    </div>

    {{-- Secondary nav (flat, matches accounts toolbar pattern) --}}
    <nav class="flex shrink-0 overflow-x-auto border-b border-gray-200">
        @foreach ($navLinks as $link)
            @php $active = request()->routeIs($link['route']); @endphp
            <a href="{{ route($link['route'], $project) }}"
                @class([
                    '-mb-px flex items-center gap-2 whitespace-nowrap px-4 py-2.5 text-sm transition-colors duration-150',
                    'border-b-2 border-omet-blue text-omet-blue font-semibold bg-blue-50/40' => $active,
                    'border-b-2 border-transparent text-gray-500 hover:text-omet-navy hover:border-gray-300' => ! $active,
                ])>
                <i data-lucide="{{ $link['icon'] }}" class="h-4 w-4"></i>
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>

    {{-- Sub-page content (table or panel) --}}
    <div>
        {{ $slot }}
    </div>

    {{-- ════════════════════════════════════════════════════════════
         RECORD INFLOW / FUNDING MODAL
         External: client collection OR borrow / project support.
         In-house: funding only — every inflow is money moved from
         another account, booked as a Transfer so ledgers stay in sync.
    ════════════════════════════════════════════════════════════ --}}
    @can('manage-financials')
    <div x-show="showNewCollection" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @keydown.escape.window="showNewCollection = false">
        <div @click.outside="showNewCollection = false"
             class="w-full max-w-xl overflow-y-auto rounded-2xl bg-white shadow-xl" style="max-height:90vh">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <div>
                    <h3 class="text-base font-semibold text-omet-navy">{{ $isExternal ? 'Record inflow' : 'Record funding' }}</h3>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        @if ($isExternal)
                            Client payments and borrowed funds are tracked separately.
                        @else
                            Borrow from another account, or take support from another project.
                        @endif
                    </p>
                </div>
                <button @click="showNewCollection = false" class="rounded p-1 text-gray-400 hover:text-gray-600" aria-label="Close">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            @if ($errors->any() && old('_form'))
            <div class="mx-6 mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[12px] text-red-800">
                <ul class="list-disc space-y-0.5 pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
            @endif

            @if ($isExternal)
            {{-- Inflow type selector --}}
            <div class="grid grid-cols-2 gap-2 px-6 pt-4" role="group" aria-label="Inflow type">
                <button type="button" @click="inflowMode = 'collection'"
                    :class="inflowMode === 'collection' ? 'border-emerald-500 bg-emerald-50/60 ring-1 ring-emerald-500' : 'border-gray-200 hover:border-gray-300'"
                    :aria-pressed="inflowMode === 'collection'"
                    class="cursor-pointer rounded-lg border p-3 text-left transition-colors duration-150">
                    <span class="flex items-center gap-1.5 text-[13px] font-semibold text-omet-navy">
                        <i data-lucide="hand-coins" class="h-4 w-4 text-emerald-600"></i> Collection
                    </span>
                    <span class="mt-0.5 block text-[11px] leading-snug text-slate-500">Payment received from the client</span>
                </button>
                <button type="button" @click="inflowMode = 'funding'"
                    :class="inflowMode === 'funding' ? 'border-indigo-500 bg-indigo-50/60 ring-1 ring-indigo-500' : 'border-gray-200 hover:border-gray-300'"
                    :aria-pressed="inflowMode === 'funding'"
                    class="cursor-pointer rounded-lg border p-3 text-left transition-colors duration-150">
                    <span class="flex items-center gap-1.5 text-[13px] font-semibold text-omet-navy">
                        <i data-lucide="arrow-left-right" class="h-4 w-4 text-indigo-600"></i> Borrow / support
                    </span>
                    <span class="mt-0.5 block text-[11px] leading-snug text-slate-500">Fund from another account or project</span>
                </button>
            </div>

            {{-- Pane 1 · Client collection (external only) --}}
            <form x-show="inflowMode === 'collection'" method="POST" action="{{ route('projects.collections.store', $project) }}" class="space-y-4 px-6 py-5"
                x-data="{
                    amount: {{ old('amount', 0) }},
                    clientType: '{{ old('client_type') }}',
                    transactionType: '{{ old('transaction_type') }}',
                    vatRate: {{ old('vat_rate', 0) }},
                    whtRate: {{ old('wht_rate', 0) }},
                    retentionRate: {{ old('retention_rate', 0) }},
                    recoupmentRate: {{ old('recoupment_rate', 0) }},
                    otherDeductions: {{ old('other_deductions_amount', 0) }},
                    applyClientType() {
                        this.vatRate = this.clientType === 'government' ? 5 : 0;
                    },
                    applyTransactionType() {
                        if (this.transactionType === 'goods') { this.whtRate = 1; this.retentionRate = 1; }
                        else if (this.transactionType === 'services') { this.whtRate = 2; this.retentionRate = 10; }
                    },
                    get totalDeductions() {
                        const a = parseFloat(this.amount) || 0;
                        const vatBase = this.clientType === 'government' ? a / 1.12 : a;
                        const vatAmt = vatBase * (parseFloat(this.vatRate) || 0) / 100;
                        const otherRateAmt = a * ((parseFloat(this.whtRate) || 0) + (parseFloat(this.retentionRate) || 0) + (parseFloat(this.recoupmentRate) || 0)) / 100;
                        return vatAmt + otherRateAmt + (parseFloat(this.otherDeductions) || 0);
                    },
                    get netAmount() {
                        return (parseFloat(this.amount) || 0) - this.totalDeductions;
                    },
                }">
                @csrf
                <input type="hidden" name="_form" value="collection">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-label for="c_date" :value="__('Date received *')" />
                        <x-input id="c_date" type="date" name="collected_on" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" :value="old('collected_on', now()->toDateString())" required />
                    </div>
                    <div>
                        <x-label for="c_amount" :value="__('Amount (PHP) *')" />
                        <x-input id="c_amount" type="number" name="amount" x-model="amount" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" :value="old('amount')" min="0.01" step="0.01" required />
                    </div>
                    <div>
                        <x-label for="c_ref" :value="__('Reference / OR no.')" />
                        <x-input id="c_ref" type="text" name="reference" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" :value="old('reference')" placeholder="e.g. OR-1234" />
                    </div>
                    <div>
                        <x-label for="c_bank" :value="__('Deposited to')" />
                        <x-searchable-select
                            id="c_bank"
                            name="bank_account_id"
                            :options="$bankAccountOptions"
                            placeholder="— none / not yet deposited —"
                            search-placeholder="Search accounts…"
                            empty-text="No accounts found"
                            clearable
                        />
                    </div>
                    <div class="col-span-2">
                        <x-label for="c_notes" :value="__('Notes')" />
                        <textarea id="c_notes" name="notes" rows="2"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-omet-blue focus:ring-omet-blue">{{ old('notes') }}</textarea>
                    </div>
                </div>

                {{-- Deductions --}}
                <div class="rounded-lg border border-amber-200 bg-amber-50/40 p-4">
                    <p class="mb-3 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-amber-800">
                        <i data-lucide="receipt-text" class="h-3.5 w-3.5"></i> Deductions
                    </p>

                    <div class="grid grid-cols-2 gap-x-4 gap-y-4">
                        <div>
                            <x-label for="c_client_type" :value="__('Client type')" />
                            <select id="c_client_type" name="client_type" x-model="clientType" @change="applyClientType()"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-omet-blue focus:ring-omet-blue">
                                <option value="">— not set —</option>
                                <option value="private">Private</option>
                                <option value="government">Government</option>
                            </select>
                            <p class="mt-1 text-[11px] text-slate-500">
                                Advance output VAT: <span class="font-semibold text-slate-700" x-text="vatRate + '%'"></span>
                                <input type="hidden" name="vat_rate" x-model="vatRate">
                            </p>
                        </div>
                        <div>
                            <x-label for="c_txn_type" :value="__('Goods or services')" />
                            <select id="c_txn_type" name="transaction_type" x-model="transactionType" @change="applyTransactionType()"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-omet-blue focus:ring-omet-blue">
                                <option value="">— not set —</option>
                                <option value="goods">Goods</option>
                                <option value="services">Services</option>
                            </select>
                            <p class="mt-1 text-[11px] text-slate-500">
                                WHT <span class="font-semibold text-slate-700" x-text="whtRate + '%'"></span> · Retention <span class="font-semibold text-slate-700" x-text="retentionRate + '%'"></span>
                                <input type="hidden" name="wht_rate" x-model="whtRate">
                                <input type="hidden" name="retention_rate" x-model="retentionRate">
                            </p>
                        </div>
                        <div>
                            <x-label for="c_recoupment_rate" :value="__('Recoupment (%)')" />
                            <x-input id="c_recoupment_rate" type="number" name="recoupment_rate" x-model="recoupmentRate" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" min="0" max="100" step="0.01" placeholder="varies per agency" />
                            <p class="mt-1 text-[11px] text-slate-500">No fixed rate — enter what applies for this agency.</p>
                        </div>
                        <div>
                            <x-label for="c_other_amt" :value="__('Other deductions (PHP)')" />
                            <x-input id="c_other_amt" type="number" name="other_deductions_amount" x-model="otherDeductions" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" min="0" step="0.01" />
                            <p class="mt-1 text-[11px] text-slate-500">No fixed amount — e.g. bank or processing fees.</p>
                        </div>
                        <div class="col-span-2">
                            <x-label for="c_other_notes" :value="__('Other deduction notes')" />
                            <x-input id="c_other_notes" type="text" name="other_deductions_notes" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" :value="old('other_deductions_notes')" placeholder="e.g. processing fee" />
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between rounded-md bg-white px-3.5 py-2.5 ring-1 ring-amber-200">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Real collection (after deductions)</span>
                        <span class="text-[14px] font-bold tabular-nums text-emerald-700" x-text="'₱' + netAmount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="showNewCollection = false"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                        class="rounded-lg bg-omet-blue px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-omet-lightblue">Save collection</button>
                </div>
            </form>
            @endif

            {{-- Pane 2 · Borrow / support — the only pane for in-house --}}
            <form @if ($isExternal) x-show="inflowMode === 'funding'" @endif
                  method="POST" action="{{ route('projects.funding.store', $project) }}" class="space-y-4 px-6 py-5">
                @csrf
                <input type="hidden" name="_form" value="funding">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <x-label for="f_from" :value="__('From account *')" />
                        <x-searchable-select
                            id="f_from"
                            name="from_account_id"
                            :options="$bankAccountOptions"
                            placeholder="— select source account —"
                            search-placeholder="Search accounts…"
                            empty-text="No accounts found"
                        />
                    </div>
                    <div class="col-span-2">
                        <x-label for="f_source_project" :value="__('Support from project (optional)')" />
                        <x-searchable-select
                            id="f_source_project"
                            name="from_project_id"
                            :options="$sourceProjectOptions"
                            placeholder="— none · plain borrowing between accounts —"
                            search-placeholder="Search projects…"
                            empty-text="No other projects found"
                            clearable
                        />
                        <p class="mt-1 text-[11px] text-gray-400">Same money movement either way — tagging a project also records the outflow on that project's books.</p>
                    </div>
                    <div class="col-span-2">
                        <x-label for="f_to" :value="__('Deposited to *')" />
                        <x-searchable-select
                            id="f_to"
                            name="to_account_id"
                            :options="$bankAccountOptions"
                            placeholder="— select receiving account —"
                            search-placeholder="Search accounts…"
                            empty-text="No accounts found"
                        />
                    </div>
                    <div>
                        <x-label for="f_date" :value="__('Date *')" />
                        <x-input id="f_date" type="date" name="date" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" :value="old('date', now()->toDateString())" required />
                    </div>
                    <div>
                        <x-label for="f_amount" :value="__('Amount (PHP) *')" />
                        <x-input id="f_amount" type="number" name="amount" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" :value="old('amount')" min="0.01" step="0.01" required />
                    </div>
                    <div class="col-span-2">
                        <x-label for="f_notes" :value="__('Notes')" />
                        <textarea id="f_notes" name="notes" rows="2"
                            placeholder="e.g. for payroll week 24 · to be returned"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-omet-blue focus:ring-omet-blue">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <p class="flex items-start gap-1.5 rounded-md bg-slate-50 px-3 py-2 text-[11px] leading-snug text-slate-500">
                    <i data-lucide="info" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i>
                    Recorded as a transfer: both bank ledgers and this project's books update together.
                </p>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="showNewCollection = false"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                        class="rounded-lg bg-omet-blue px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-omet-lightblue">Save funding</button>
                </div>
            </form>
        </div>
    </div>
    @endcan


    {{-- ════════════════════════════════════════════════════════════
         EDIT PROJECT MODAL
    ════════════════════════════════════════════════════════════ --}}
    <div x-show="showEdit" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @keydown.escape.window="showEdit = false">
        <div @click.outside="showEdit = false"
             class="w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-xl"
             style="max-height:90vh">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h3 class="text-base font-semibold text-omet-navy">Edit project</h3>
                <button @click="showEdit = false" class="rounded p-1 text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('projects.update', $project) }}" class="space-y-4 px-6 py-5">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <x-label for="ep_name" :value="__('Project name *')" />
                        <x-input id="ep_name" type="text" name="name" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"
                            value="{{ old('name', $project->name) }}" required />
                    </div>
                    <div class="col-span-2">
                        <x-label for="ep_status" :value="__('Status')" />
                        <select id="ep_status" name="status"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-omet-blue focus:ring-omet-blue">
                            @php $sel = old('status', $project->status); @endphp
                            @foreach ($statusLabels as $val => $lbl)
                                <option value="{{ $val }}" {{ $sel === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($isExternal)
                    <div>
                        <x-label for="ep_client" :value="__('Client name')" />
                        <x-input id="ep_client" type="text" name="client_name" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"
                            value="{{ old('client_name', $project->client_name) }}" />
                    </div>
                    <div>
                        <x-label for="ep_location" :value="__('Location')" />
                        <x-input id="ep_location" type="text" name="location" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"
                            value="{{ old('location', $project->location) }}" />
                    </div>
                    @else
                    <div class="col-span-2">
                        <x-label for="ep_location" :value="__('Location')" />
                        <x-input id="ep_location" type="text" name="location" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"
                            value="{{ old('location', $project->location) }}" />
                    </div>
                    @endif
                    <div class="col-span-2">
                        <x-label for="ep_cv" :value="__($isExternal ? 'Contract value (PHP)' : 'Budget (PHP) — optional')" />
                        <div class="relative mt-1">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-gray-400">₱</span>
                            <x-input id="ep_cv" type="number" name="contract_value" class="block w-full rounded-lg border-gray-300 pl-7 text-sm"
                                value="{{ old('contract_value', (float) $project->contract_value) }}" min="0" step="0.01" />
                        </div>
                        @unless ($isExternal)
                        <p class="mt-1 text-xs text-gray-400">Set a budget to track utilization; leave blank to just record spending.</p>
                        @endunless
                    </div>
                    <div>
                        <x-label for="ep_start" :value="__('Start date')" />
                        <x-input id="ep_start" type="date" name="start_date" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"
                            value="{{ old('start_date', $project->start_date?->toDateString()) }}" />
                    </div>
                    <div>
                        <x-label for="ep_end" :value="__('End date')" />
                        <x-input id="ep_end" type="date" name="end_date" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"
                            value="{{ old('end_date', $project->end_date?->toDateString()) }}" />
                    </div>
                    <div class="col-span-2">
                        <x-label for="ep_due" :value="__('Due date')" />
                        <x-input id="ep_due" type="date" name="due_date" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"
                            value="{{ old('due_date', $project->due_date?->toDateString()) }}" />
                        <p class="mt-1 text-xs text-gray-400">Target delivery date — can differ from end date.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showEdit = false"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-omet-blue px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-omet-lightblue">
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
