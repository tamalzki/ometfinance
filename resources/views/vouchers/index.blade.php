<x-app-layout page-title="Daily Transactions">
@php
    $peso = fn ($n) => '₱' . number_format((float) $n, 2);

    $statusTone = [
        'draft'     => 'bg-slate-100 text-slate-600 ring-slate-200',
        'unpaid'    => 'bg-amber-50 text-amber-800 ring-amber-100',
        'partial'   => 'bg-blue-50 text-blue-700 ring-blue-100',
        'pdc'       => 'bg-violet-50 text-violet-700 ring-violet-100',
        'paid'      => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        'cancelled' => 'bg-rose-50 text-rose-600 ring-rose-100',
    ];

    $accountsForPicker = $accounts->map(fn ($a) => [
        'id'     => $a->id,
        'label'  => ($a->entity?->name ? $a->entity->name . ' — ' : '') . $a->name,
        'search' => strtolower(implode(' ', array_filter([
            $a->entity?->name, $a->name, $a->bank_name, (string) ($a->account_number ?? ''),
        ]))),
    ])->values();

    $projectsForPicker = $projects->map(fn ($p) => [
        'id'     => $p->id,
        'label'  => $p->name . ($p->code ? ' (' . $p->code . ')' : ''),
        'kind'   => $p->kind === 'in_house' ? 'In-house' : 'External',
        'search' => strtolower(implode(' ', array_filter([
            $p->name, $p->code, $p->client_name, $p->kind === 'in_house' ? 'in-house' : 'external',
        ]))),
    ])->values();

    $payeesForPicker = $payees->map(fn ($name) => [
        'id'     => $name,
        'label'  => $name,
        'search' => strtolower($name),
    ])->values();

    $typesForPicker = collect($types)->map(fn ($label, $key) => [
        'id'     => $key,
        'label'  => $label,
        'search' => strtolower($key . ' ' . $label),
    ])->values();

    $modesForPicker = collect($modes)->map(fn ($label, $key) => [
        'id'     => $key,
        'label'  => $label,
        'search' => strtolower($key . ' ' . $label),
    ])->values();

    $formDefaults = [
        'voucher_no'             => old('voucher_no', ''),
        'voucher_date'           => old('voucher_date', now()->format('Y-m-d')),
        'due_date'               => old('due_date', ''),
        'release_date'           => old('release_date', ''),
        'payee_name'             => old('payee_name', ''),
        'project_id'             => old('project_id', $activeProject ? (string) $activeProject->id : ''),
        'source_bank_account_id' => old('source_bank_account_id', ''),
        'transaction_type'       => old('transaction_type', 'rfp'),
        'po_number'              => old('po_number', ''),
        'reference'              => old('reference', ''),
        'amount_payable'         => old('amount_payable', ''),
        'mode_of_payment'        => old('mode_of_payment', 'cash'),
        'particular'             => old('particular', ''),
        'remarks'                => old('remarks', ''),
        'source_of_fund'         => old('source_of_fund', ''),
        'or_ref'                 => old('or_ref', ''),
        'change_amount'          => old('change_amount', ''),
        'notes'                  => old('notes', ''),
        'payment_status'         => old('payment_status', 'unpaid'),
        'attachments'            => [],
    ];

    $oldPayee = $formDefaults['payee_name'];
    $payeeOtherInitial = $oldPayee !== '' && ! $payees->contains($oldPayee);

    // Arriving from a project's Outflow tab via the "Outflow" button opens
    // the Add Voucher form pre-filled with that project, per the "all
    // outflow must have a voucher" rule.
    $showFormInitial = $errors->any()
        ? ! old('paying_voucher_id')
        : ($newVoucher && $activeProject !== null);
@endphp

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('vouchersPage', () => ({
        showForm: @json($showFormInitial),
        showPay: false,
        editId: @json(old('editing_voucher_id') ? (int) old('editing_voucher_id') : null),
        lockedFields: false,
        payVoucher: { id: null, no: '', payee: '', balance: 0 },
        q: '',

        projects: @json($projectsForPicker),
        accounts: @json($accountsForPicker),
        types: @json($typesForPicker),
        modes: @json($modesForPicker),
        payees: @json($payeesForPicker),
        categories: @json($categoriesForPicker),
        statuses: @json($statuses),

        projOpen: false, projQuery: '',
        acctOpen: false, acctQuery: '',
        typeOpen: false, typeQuery: '',
        modeOpen: false, modeQuery: '',
        payeeOpen: false, payeeQuery: '', payeeOther: @json($payeeOtherInitial),
        categoryOpen: false, categoryQuery: '',

        // header form model
        f: @json($formDefaults),

        // payment form model
        p: { bank_account_id: '', paid_on: @json(now()->format('Y-m-d')), amount: '', mode: 'cash', check_no: '', check_date: '', notes: '' },

        closeFormCombos() {
            this.projOpen = false;
            this.acctOpen = false;
            this.typeOpen = false;
            this.modeOpen = false;
            this.payeeOpen = false;
            this.categoryOpen = false;
            this.projQuery = '';
            this.acctQuery = '';
            this.typeQuery = '';
            this.modeQuery = '';
            this.payeeQuery = '';
            this.categoryQuery = '';
        },
        filteredOptions(list, query) {
            const needle = (query || '').trim().toLowerCase();
            if (! needle) return list;
            return list.filter(o => (o.search || o.label || '').toLowerCase().includes(needle));
        },
        projectLabel(id) {
            if (! id) return '— none —';
            const p = this.projects.find(x => String(x.id) === String(id));
            return p ? p.label : '— none —';
        },
        accountLabel(id) {
            if (! id) return 'Pending — source not yet confirmed';
            const a = this.accounts.find(x => String(x.id) === String(id));
            return a ? a.label : 'Pending — source not yet confirmed';
        },
        typeLabel(id) {
            const t = this.types.find(x => x.id === id);
            return t ? t.label : '— select type —';
        },
        modeLabel(id) {
            const m = this.modes.find(x => x.id === id);
            return m ? m.label : '— select mode —';
        },
        categoryLabel(id) {
            if (! id) return '— select category —';
            const c = this.categories.find(x => String(x.id) === String(id));
            return c ? c.label : '— select category —';
        },
        statusLabel(status) {
            return this.statuses[status] || status;
        },
        get alreadyPaid() {
            return this.f.payment_status === 'paid';
        },
        get isCancelled() {
            return this.f.voucher_status === 'cancelled';
        },
        get hasPartialPayment() {
            return ['partial', 'pdc'].includes(this.f.voucher_status);
        },
        formatPeso(n) {
            return '₱' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        openAdd() {
            this.editId = null;
            this.lockedFields = false;
            this.payeeOther = false;
            this.closeFormCombos();
            this.f = {
                voucher_no: '', voucher_date: @json(now()->format('Y-m-d')),
                due_date: '', release_date: '', payee_name: '', project_id: '', source_bank_account_id: '',
                transaction_type: 'rfp', category_id: '', po_number: '', reference: '', amount_payable: '',
                mode_of_payment: 'cash', particular: '',
                remarks: '', source_of_fund: '', or_ref: '', change_amount: '', notes: '',
                payment_status: 'unpaid', voucher_status: '', balance_due: 0,
                attachments: [],
            };
            this.showForm = true;
        },
        openEdit(v) {
            this.editId = v.id;
            this.lockedFields = !!v.has_payments;
            this.payeeOther = !!v.payee_name && ! this.payees.some(p => p.label === v.payee_name);
            this.closeFormCombos();
            this.f = {
                voucher_no: v.voucher_no,
                voucher_date: v.voucher_date,
                due_date: v.due_date || '',
                release_date: v.release_date || '',
                payee_name: v.payee_name,
                project_id: v.project_id ? String(v.project_id) : '',
                source_bank_account_id: v.source_bank_account_id ? String(v.source_bank_account_id) : '',
                transaction_type: v.transaction_type || 'rfp',
                category_id: v.category_id ? String(v.category_id) : '',
                po_number: v.po_number || '',
                reference: v.reference || '',
                amount_payable: String(v.amount_payable),
                mode_of_payment: v.mode_of_payment || 'cash',
                particular: v.particular || '',
                remarks: v.remarks || '',
                source_of_fund: v.source_of_fund || '',
                or_ref: v.or_ref || '',
                change_amount: v.change_amount ? String(v.change_amount) : '',
                notes: v.notes || '',
                payment_status: v.status === 'paid' ? 'paid' : 'unpaid',
                voucher_status: v.status || '',
                balance_due: v.balance ?? 0,
                attachments: v.attachments || [],
            };
            this.showForm = true;
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
        attachmentError: '',
        validateAttachments(input) {
            const maxBytes = 10 * 1024 * 1024;
            const files = Array.from(input.files || []);
            const oversized = files.filter(f => f.size > maxBytes).map(f => f.name);
            if (oversized.length) {
                this.attachmentError = oversized.join(', ');
                input.value = '';
            } else {
                this.attachmentError = '';
            }
        },
        closeForm() {
            this.showForm = false;
            this.editId = null;
            this.attachmentError = '';
            this.closeFormCombos();
        },
        closePay() { this.showPay = false; },
    }));
});
</script>

<div x-data="vouchersPage" class="flex min-h-0 min-w-0 flex-1 flex-col gap-2.5">

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
<div class="flex shrink-0 flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        @if ($activeProject)
            <a href="{{ route('projects.show.outflow', $activeProject) }}"
               class="mb-0.5 inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 transition hover:text-omet-navy">
                <i data-lucide="arrow-left" class="h-3 w-3"></i> Back to {{ $activeProject->name }}
            </a>
        @endif
        <h1 class="text-xl font-bold tracking-tight text-omet-navy">Daily Transactions</h1>
        <p class="text-xs text-slate-500 flex flex-wrap items-center gap-1.5">
            <span>{{ $summary['count'] }} shown · {{ $peso($summary['outstanding']) }} outstanding</span>
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
    <div class="flex items-center gap-2">
        <a href="{{ route('vouchers.payables') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-omet-blue hover:text-omet-blue">
            <i data-lucide="alarm-clock" class="h-4 w-4"></i> Payables
        </a>
        <button type="button" @click="openAdd()"
                class="inline-flex items-center gap-1.5 rounded-lg bg-omet-blue px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-omet-lightblue">
            <i data-lucide="plus" class="h-4 w-4"></i> Add Voucher
        </button>
    </div>
</div>

{{-- ── Summary cards ────────────────────────────────────────────────────── --}}
<div class="grid shrink-0 grid-cols-2 gap-3 lg:grid-cols-4">
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

{{-- ── Toolbar ──────────────────────────────────────────────────────────── --}}
<div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
    <div class="relative w-72">
        <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        <input type="search" x-model="q" autocomplete="off" placeholder="Search payee, number, project"
               class="h-9 w-full rounded-md border border-slate-200 bg-white pl-8 pr-3 text-[12.5px] text-slate-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
    </div>
    <form method="GET" action="{{ route('vouchers.index') }}" class="flex flex-wrap items-center gap-1.5">
        {{-- Status select with aligned custom arrow --}}
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
        @if ($activeStatus)
            <a href="{{ route('vouchers.index') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <i data-lucide="x" class="h-3 w-3"></i> Clear
            </a>
        @endif
    </form>
</div>

{{-- ── Table ────────────────────────────────────────────────────────────── --}}
<div class="data-grid min-h-0 min-w-0 flex-1 overflow-auto">
    <table class="min-w-full">
        <thead class="sticky top-0 z-20">
            <tr>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[108px]">Voucher</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[96px]">Date</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[96px]">Due</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Payee / Particular</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Project</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[120px]">Amount</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[110px]">Status</th>
                <th class="sticky right-0 z-30 border-b border-l border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 min-w-[15rem]">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($vouchers as $v)
                @php
                    $amountPaid = $v->amountPaid();
                    $balance = $v->balanceDue();
                    $overdue = $v->isOverdue();
                    $haystack = strtolower(implode(' ', array_filter([
                        $v->voucher_no, $v->payee_name, $v->project?->name, $v->typeLabel(), $v->po_number, $v->reference,
                    ])));
                    $payload = [
                        'id' => $v->id, 'voucher_no' => $v->voucher_no,
                        'voucher_date' => $v->voucher_date->format('Y-m-d'),
                        'due_date' => $v->due_date?->format('Y-m-d'),
                        'release_date' => $v->release_date?->format('Y-m-d'),
                        'payee_name' => $v->payee_name, 'project_id' => $v->project_id,
                        'source_bank_account_id' => $v->source_bank_account_id,
                        'transaction_type' => $v->transaction_type, 'category_id' => $v->category_id,
                        'po_number' => $v->po_number,
                        'reference' => $v->reference,
                        'amount_payable' => (float) $v->amount_payable, 'mode_of_payment' => $v->mode_of_payment,
                        'status' => $v->status, 'particular' => $v->particular,
                        'remarks' => $v->remarks, 'source_of_fund' => $v->source_of_fund,
                        'or_ref' => $v->or_ref, 'change_amount' => (float) ($v->change_amount ?? 0),
                        'notes' => $v->notes,
                        'balance' => $balance,
                        'has_payments' => $v->payments->isNotEmpty(),
                        'attachments' => $v->attachments->map(fn ($a) => [
                            'id' => $a->id,
                            'name' => $a->original_name,
                            'size' => $a->humanSize(),
                        ])->values(),
                    ];
                @endphp
                <tr class="group cursor-pointer transition-colors hover:bg-slate-50/70"
                    x-show="q.trim() === '' || @js($haystack).includes(q.trim().toLowerCase())"
                    @click="window.location = '{{ route('vouchers.show', $v->id) }}'">
                    <td class="border-b border-slate-100 px-4 py-2.5 align-top text-[12.5px] font-semibold text-slate-700 whitespace-nowrap">{{ $v->voucher_no }}</td>
                    <td class="border-b border-slate-100 px-4 py-2.5 align-top tabular-nums text-[12px] text-slate-600 whitespace-nowrap">{{ $v->voucher_date->format('M d, Y') }}</td>
                    <td class="border-b border-slate-100 px-4 py-2.5 align-top tabular-nums text-[12px] whitespace-nowrap {{ $overdue ? 'font-semibold text-rose-600' : 'text-slate-600' }}">
                        {{ $v->due_date?->format('M d, Y') ?? '—' }}
                        @if ($overdue)<span class="block text-[10px] font-medium text-rose-500">overdue</span>@endif
                    </td>
                    <td class="border-b border-slate-100 px-4 py-2.5 align-top">
                        <p class="text-[13px] font-medium text-slate-700">{{ $v->payee_name }}</p>
                        @if ($v->particular)
                            <p class="mt-0.5 max-w-[220px] truncate text-[11px] text-slate-400">{{ $v->particular }}</p>
                        @endif
                    </td>
                    <td class="border-b border-slate-100 px-4 py-2.5 align-top text-[12.5px] text-slate-600">
                        @if ($v->project)
                            <a href="{{ route('projects.show', $v->project) }}" @click.stop class="text-[12px] font-medium text-omet-blue hover:underline">{{ $v->project->name }}</a>
                        @else <span class="text-slate-300">—</span> @endif
                    </td>
                    <td class="border-b border-slate-100 px-4 py-2.5 align-top text-right text-[12.5px] font-semibold tabular-nums text-omet-navy whitespace-nowrap">{{ $peso($v->amount_payable) }}</td>
                    <td class="border-b border-slate-100 px-4 py-2.5 align-top">
                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $statusTone[$v->status] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $v->statusLabel() }}</span>
                    </td>
                    <td class="sticky right-0 z-10 border-b border-l border-slate-200 bg-white px-3 py-2.5 align-middle group-hover:bg-slate-50" @click.stop>
                        <div class="flex flex-row flex-nowrap items-center justify-end gap-1.5">
                            @if ($v->isOpen())
                                <button type="button" @click="openPay({{ \Illuminate\Support\Js::from($payload) }})"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100">
                                    <i data-lucide="banknote" class="h-3 w-3 pointer-events-none"></i> Pay
                                </button>
                            @endif
                            <button type="button" @click="openEdit({{ \Illuminate\Support\Js::from($payload) }})"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                                <i data-lucide="pencil" class="h-3 w-3 pointer-events-none"></i> Edit
                            </button>
                            @if ($v->isOpen() && $v->payments->isEmpty())
                                <form method="POST" action="{{ route('vouchers.cancel', $v->id) }}"
                                      onsubmit="return confirm('Cancel voucher {{ $v->voucher_no }}? It will be excluded from payables and can be reactivated later.');" class="inline-flex shrink-0">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-600 shadow-sm transition hover:bg-rose-100">
                                        <i data-lucide="ban" class="h-3 w-3 pointer-events-none"></i> Cancel
                                    </button>
                                </form>
                            @elseif ($v->status === 'cancelled')
                                <form method="POST" action="{{ route('vouchers.reactivate', $v->id) }}" class="inline-flex shrink-0">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                                        <i data-lucide="rotate-ccw" class="h-3 w-3 pointer-events-none"></i> Reactivate
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('vouchers.destroy', $v->id) }}"
                                  onsubmit="return confirm('Delete voucher {{ $v->voucher_no }}? Any posted payments will be reversed.');" class="inline-flex shrink-0">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-600 shadow-sm transition hover:bg-red-100">
                                    <i data-lucide="trash-2" class="h-3 w-3 pointer-events-none"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-6 py-14 text-center">
                        <i data-lucide="receipt" class="mx-auto mb-2 h-8 w-8 text-slate-200"></i>
                        <p class="text-xs text-slate-400">No transactions yet. Use <span class="font-semibold text-omet-blue">Add Voucher</span>.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('vouchers.partials.form-modal')
@include('vouchers.partials.payment-modal')

</div>
</x-app-layout>
