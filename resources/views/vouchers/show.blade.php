<x-app-layout :page-title="'Voucher ' . $voucher->voucher_no">
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

    $amountPaid = $voucher->amountPaid();
    $balance    = $voucher->balanceDue();
    $overdue    = $voucher->isOverdue();

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

    $voucherPayload = [
        'id'                     => $voucher->id,
        'voucher_no'             => $voucher->voucher_no,
        'voucher_date'           => $voucher->voucher_date->format('Y-m-d'),
        'due_date'               => $voucher->due_date?->format('Y-m-d'),
        'release_date'           => $voucher->release_date?->format('Y-m-d'),
        'payee_name'             => $voucher->payee_name,
        'project_id'             => $voucher->project_id,
        'source_bank_account_id' => $voucher->source_bank_account_id,
        'transaction_type'       => $voucher->transaction_type,
        'category_id'            => $voucher->category_id,
        'po_number'              => $voucher->po_number,
        'reference'              => $voucher->reference,
        'amount_payable'         => (float) $voucher->amount_payable,
        'mode_of_payment'        => $voucher->mode_of_payment,
        'status'                 => $voucher->status,
        'particular'             => $voucher->particular,
        'remarks'                => $voucher->remarks,
        'source_of_fund'         => $voucher->source_of_fund,
        'or_ref'                 => $voucher->or_ref,
        'change_amount'          => (float) ($voucher->change_amount ?? 0),
        'notes'                  => $voucher->notes,
        'balance'                => $balance,
        'has_payments'           => $voucher->payments->isNotEmpty(),
        'attachments'            => $voucher->attachments->map(fn ($a) => [
            'id'   => $a->id,
            'name' => $a->original_name,
            'size' => $a->humanSize(),
        ])->values(),
    ];
@endphp

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('vouchersPage', () => ({
        showForm: false,
        editId: null,
        lockedFields: false,
        payeeOther: false,

        projects: @json($projectsForPicker),
        accounts: @json($accountsForPicker),
        types: @json($typesForPicker),
        modes: @json($modesForPicker),
        payees: @json($payeesForPicker),
        categories: @json($categoriesForPicker),
        statuses: @json(\App\Models\Voucher::STATUSES),

        projOpen: false, projQuery: '',
        acctOpen: false, acctQuery: '',
        typeOpen: false, typeQuery: '',
        modeOpen: false, modeQuery: '',
        payeeOpen: false, payeeQuery: '',
        categoryOpen: false, categoryQuery: '',

        f: {},
        attachmentError: '',

        closeFormCombos() {
            this.projOpen = false; this.acctOpen = false;
            this.typeOpen = false; this.modeOpen = false;
            this.payeeOpen = false; this.categoryOpen = false;
            this.projQuery = ''; this.acctQuery = '';
            this.typeQuery = ''; this.modeQuery = '';
            this.payeeQuery = ''; this.categoryQuery = '';
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
        get alreadyPaid() { return this.f.payment_status === 'paid'; },
        get isCancelled() { return this.f.voucher_status === 'cancelled'; },
        get hasPartialPayment() { return ['partial', 'pdc'].includes(this.f.voucher_status); },
        formatPeso(n) {
            return '₱' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
    }));
});
</script>

<div x-data="vouchersPage" class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-y-auto pb-4">

    {{-- ── Back link ───────────────────────────────────────────────────── --}}
    <a href="{{ route('vouchers.index') }}" class="inline-flex w-fit items-center gap-1.5 text-[12px] font-medium text-slate-500 transition hover:text-omet-blue">
        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> Back to Daily Transactions
    </a>

    {{-- ── Alerts ──────────────────────────────────────────────────────── --}}
    @if (session('success'))
    <div class="shrink-0 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-xs font-medium text-green-800">
        {{ session('success') }}
    </div>
    @endif
    @if ($errors->any())
    <div class="shrink-0 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
    @endif

    {{-- ── Header / summary ───────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10.5px] font-semibold uppercase tracking-wider text-omet-blue">Disbursement</p>
                <h1 class="mt-0.5 text-lg font-semibold text-omet-navy">{{ $voucher->voucher_no }} · {{ $voucher->payee_name }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-[12px] text-gray-500">
                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $statusTone[$voucher->status] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $voucher->statusLabel() }}</span>
                    @if ($overdue)
                        <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-600 ring-1 ring-rose-100">Overdue</span>
                    @endif
                    <span>Voucher date {{ $voucher->voucher_date->format('M d, Y') }}</span>
                    @if ($voucher->due_date)<span>· Due {{ $voucher->due_date->format('M d, Y') }}</span>@endif
                </p>
            </div>
            <div class="flex flex-wrap items-start gap-3">
                <button type="button"
                        @click="openEdit({{ \Illuminate\Support\Js::from($voucherPayload) }})"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12.5px] font-semibold text-slate-600 shadow-sm transition hover:border-omet-blue hover:text-omet-blue">
                    <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit
                </button>
            </div>
            <div class="flex flex-wrap gap-4 text-right">
                <div>
                    <p class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Payable</p>
                    <p class="text-[15px] font-semibold tabular-nums text-omet-navy">{{ $peso($voucher->amount_payable) }}</p>
                </div>
                <div>
                    <p class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Paid</p>
                    <p class="text-[15px] font-semibold tabular-nums {{ $amountPaid > 0 ? 'text-emerald-700' : 'text-slate-300' }}">{{ $amountPaid > 0 ? $peso($amountPaid) : '—' }}</p>
                </div>
                <div>
                    <p class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Balance</p>
                    <p class="text-[15px] font-semibold tabular-nums {{ $balance > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $peso($balance) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Voucher details ─────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Voucher information</h3>
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Voucher date</dt>
                    <dd class="text-[13px] text-slate-700">{{ $voucher->voucher_date->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Due date</dt>
                    <dd class="text-[13px] text-slate-700">{{ $voucher->due_date?->format('M d, Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Release date</dt>
                    <dd class="text-[13px] text-slate-700">{{ $voucher->release_date?->format('M d, Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Project</dt>
                    <dd class="text-[13px] text-slate-700">
                        @if ($voucher->project)
                            <a href="{{ route('projects.show', $voucher->project) }}" class="font-medium text-omet-blue hover:underline">{{ $voucher->project->name }}</a>
                        @else — @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Category</dt>
                    <dd class="text-[13px] text-slate-700">{{ $voucher->category?->fullLabel() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Type</dt>
                    <dd class="text-[13px] text-slate-700">{{ $voucher->typeLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Mode of payment</dt>
                    <dd class="text-[13px] text-slate-700">{{ $voucher->modeLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Source bank account</dt>
                    <dd class="text-[13px] text-slate-700">
                        @if ($voucher->sourceBankAccount)
                            {{ $voucher->sourceBankAccount->entity?->name ? $voucher->sourceBankAccount->entity->name . ' — ' : '' }}{{ $voucher->sourceBankAccount->name }}
                        @else — @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">PO Number</dt>
                    <dd class="text-[13px] text-slate-700">{{ $voucher->po_number ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Reference (PR / OR / SI)</dt>
                    <dd class="text-[13px] text-slate-700">{{ $voucher->reference ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Notes &amp; remarks</h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Particular / description</dt>
                    <dd class="whitespace-pre-line text-[13px] text-slate-700">{{ $voucher->particular ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Remarks</dt>
                    <dd class="whitespace-pre-line text-[13px] text-slate-700">{{ $voucher->remarks ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Source of fund</dt>
                    <dd class="whitespace-pre-line text-[13px] text-slate-700">{{ $voucher->source_of_fund ?: '—' }}</dd>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">OR / CR / SI / CI ref.</dt>
                        <dd class="text-[13px] text-slate-700">{{ $voucher->or_ref ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10.5px] font-medium uppercase tracking-wide text-slate-400">Change / excess</dt>
                        <dd class="text-[13px] text-slate-700">{{ $voucher->change_amount ? $peso($voucher->change_amount) : '—' }}</dd>
                    </div>
                </div>
            </dl>
        </div>
    </div>

    {{-- ── Payment history ─────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
            <i data-lucide="banknote" class="h-3.5 w-3.5"></i> Payment history
            <span class="font-normal normal-case text-slate-400">({{ $voucher->payments->count() }})</span>
        </h3>

        @if ($voucher->payments->isEmpty())
            <p class="rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-[12px] text-slate-400">No payments recorded yet.</p>
        @else
            <div class="overflow-hidden rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-100 text-[12px]">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-500">Date</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-500">Amount</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-500">Mode / check</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-500">Account</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-500">Notes</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-500">Reverse</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($voucher->payments as $p)
                            <tr class="align-top">
                                <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $p->paid_on?->format('M d, Y') }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums text-omet-navy">{{ $peso($p->amount) }}</td>
                                <td class="px-3 py-2 text-slate-600">
                                    {{ \App\Models\Voucher::MODES[$p->mode] ?? ($p->mode ? ucfirst($p->mode) : '—') }}
                                    @if ($p->check_no)
                                        <span class="block text-[10.5px] text-slate-400">Check {{ $p->check_no }}@if($p->check_date) · {{ $p->check_date->format('M d, Y') }}@endif</span>
                                    @endif
                                    @if ($p->isPostDated())
                                        <span class="mt-0.5 inline-block rounded-full bg-violet-50 px-1.5 py-0.5 text-[10px] font-semibold text-violet-700 ring-1 ring-violet-100 ring-inset">PDC</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-500">{{ $p->bankAccount?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ $p->notes ?: '—' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <form method="POST" action="{{ route('vouchers.payments.destroy', $p->id) }}"
                                          onsubmit="return confirm('Reverse this payment of {{ $peso($p->amount) }}? The bank ledger and project rows it created will be removed.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-red-200 bg-red-50 px-2 py-1 text-[10.5px] font-semibold text-red-600 shadow-sm transition hover:bg-red-100">
                                            <i data-lucide="undo-2" class="h-3 w-3 pointer-events-none"></i> Reverse
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Record payment --}}
        @if ($voucher->isOpen())
        <div class="mt-4 border-t border-slate-100 pt-4">
            <h4 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Record a payment</h4>
            <form method="POST" action="{{ route('vouchers.payments.store', $voucher) }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Pay from bank account *</label>
                        <select name="bank_account_id" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                            <option value="">— pick account (deducts its balance) —</option>
                            @foreach ($accounts as $a)
                                <option value="{{ $a->id }}" {{ old('bank_account_id', $voucher->source_bank_account_id) == $a->id ? 'selected' : '' }}>
                                    {{ $a->entity?->name ? $a->entity->name . ' — ' : '' }}{{ $a->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Amount (PHP) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount', $balance > 0 ? $balance : '') }}" placeholder="0.00"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] tabular-nums text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Paid on *</label>
                        <input type="date" name="paid_on" required value="{{ old('paid_on', now()->format('Y-m-d')) }}"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Mode</label>
                        <select name="mode" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                            @foreach ($modes as $k => $label)
                                <option value="{{ $k }}" {{ old('mode', $voucher->mode_of_payment) === $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Check no.</label>
                        <input type="text" name="check_no" value="{{ old('check_no') }}"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Check date <span class="text-gray-400">(future = PDC)</span></label>
                        <input type="date" name="check_date" value="{{ old('check_date') }}"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-600">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes') }}"
                           class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2 text-[12.5px] font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                        <i data-lucide="banknote" class="h-3.5 w-3.5"></i> Record payment
                    </button>
                </div>
            </form>
        </div>
        @endif
    </div>

    {{-- ── Attachments ──────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
            <i data-lucide="paperclip" class="h-3.5 w-3.5"></i> Attachments
            <span class="font-normal normal-case text-slate-400">({{ $voucher->attachments->count() }})</span>
        </h3>

        @if ($voucher->attachments->isNotEmpty())
            <ul class="mb-3 divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
                @foreach ($voucher->attachments as $a)
                    <li class="flex items-center justify-between gap-3 px-3 py-2 text-[12px]">
                        <a href="{{ route('vouchers.attachments.download', $a) }}" class="flex min-w-0 items-center gap-2 text-slate-700 hover:text-omet-blue hover:underline">
                            <i data-lucide="file-text" class="h-3.5 w-3.5 shrink-0 text-slate-400"></i>
                            <span class="truncate">{{ $a->original_name }}</span>
                        </a>
                        <div class="flex shrink-0 items-center gap-2 text-[10.5px] text-slate-400">
                            <span>{{ $a->humanSize() }}</span>
                            <span>·</span>
                            <span>{{ $a->created_at->format('M d, Y') }}</span>
                            <form method="POST" action="{{ route('vouchers.attachments.destroy', $a) }}"
                                  onsubmit="return confirm('Remove &quot;{{ addslashes($a->original_name) }}&quot;?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-md p-1 text-slate-400 transition hover:bg-red-50 hover:text-red-600">
                                    <i data-lucide="trash-2" class="h-3 w-3 pointer-events-none"></i>
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mb-3 rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-[12px] text-slate-400">No supporting documents attached yet.</p>
        @endif

        @error('file')
            <p class="mb-2 text-[11px] font-medium text-red-600">{{ $message }}</p>
        @enderror

        <div x-data="{ fileError: '', checkFile(input) {
            const f = input.files[0];
            if (f && f.size > 10 * 1024 * 1024) {
                this.fileError = f.name;
                input.value = '';
            } else {
                this.fileError = '';
            }
        } }">
            <form method="POST" enctype="multipart/form-data" action="{{ route('vouchers.attachments.store', $voucher) }}"
                  class="flex items-center gap-2"
                  @submit.prevent="if (fileError) { return; } $el.submit()">
                @csrf
                <input type="file" name="file" required
                       @change="checkFile($event.target)"
                       class="block w-full flex-1 text-[12px] text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-omet-blue/10 file:px-3 file:py-1.5 file:text-[11.5px] file:font-semibold file:text-omet-blue hover:file:bg-omet-blue/20">
                <button type="submit" :disabled="!!fileError"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-omet-blue px-3 py-1.5 text-[11.5px] font-semibold text-white shadow-sm transition hover:bg-omet-lightblue disabled:cursor-not-allowed disabled:opacity-50">
                    <i data-lucide="upload" class="h-3.5 w-3.5"></i> Upload
                </button>
            </form>
            <p class="mt-1 text-[10.5px] text-slate-400">PDF, image, Word or Excel files up to 10 MB — invoices, ORs, signed checks, approval slips.</p>

            {{-- Error modal --}}
            <div x-show="fileError" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-start gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100">
                            <i data-lucide="alert-circle" class="h-5 w-5 text-red-600"></i>
                        </div>
                        <div>
                            <h3 class="text-[14px] font-semibold text-slate-800">File too large</h3>
                            <p class="mt-1 text-[12.5px] text-slate-600">
                                <span class="font-medium" x-text="fileError"></span> exceeds the 10 MB limit.
                                Please compress the file or split it into smaller parts before uploading.
                            </p>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end">
                        <button @click="fileError = ''" type="button"
                                class="rounded-lg bg-omet-blue px-5 py-2 text-[12.5px] font-semibold text-white transition hover:bg-omet-lightblue">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

@include('vouchers.partials.form-modal')

</div>
</x-app-layout>
