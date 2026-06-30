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
        'source'                 => $voucher->source,
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
            this.lockedFields = false;
            this.payeeOther = !!v.payee_name && ! this.payees.some(p => p.label === v.payee_name);
            this.closeFormCombos();
            this.f = {
                voucher_no: v.voucher_no,
                voucher_date: v.voucher_date,
                due_date: v.due_date || '',
                release_date: v.release_date || '',
                payee_name: v.payee_name,
                source: v.source || '',
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

    {{-- ── Toolbar ─────────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2 min-w-0">
            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $statusTone[$voucher->status] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $voucher->statusLabel() }}</span>
            @if ($voucher->isPendingApproval())
                <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-100"><i data-lucide="hourglass" class="h-3 w-3"></i> For Approval</span>
            @elseif ($voucher->isApprovalRejected())
                <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-600 ring-1 ring-rose-100"><i data-lucide="x-circle" class="h-3 w-3"></i> Rejected</span>
            @elseif ($pendingRequest = $voucher->pendingRequest())
                <span class="inline-flex items-center gap-1 rounded-md bg-violet-50 px-2 py-0.5 text-[11px] font-semibold text-violet-700 ring-1 ring-violet-100">
                    <i data-lucide="git-pull-request" class="h-3 w-3"></i> {{ $pendingRequest->typeLabel() }} Pending Review
                </span>
            @endif
            @if ($overdue)
                <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-600 ring-1 ring-rose-100">Overdue</span>
            @endif
            <span class="text-[12px] text-slate-500">
                Payable {{ $peso($voucher->amount_payable) }}
                · Paid {{ $amountPaid > 0 ? $peso($amountPaid) : '—' }}
                · Balance {{ $peso($balance) }}
            </span>
        </div>
        <div class="disburse-page-actions shrink-0">
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12.5px] font-semibold text-slate-600 shadow-sm transition hover:border-omet-blue hover:text-omet-blue">
                <i data-lucide="printer" class="h-3.5 w-3.5"></i> Print
            </button>
            <a href="{{ route('vouchers.edit', $voucher) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12.5px] font-semibold text-slate-600 shadow-sm transition hover:border-omet-blue hover:text-omet-blue">
                <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit
            </a>
        </div>
    </div>

    {{-- ── Rejection notice ───────────────────────────────────────────────── --}}
    @if ($latestRequest = $voucher->latestRequest())
        @if ($latestRequest->status === \App\Models\VoucherRequest::STATUS_REJECTED)
        <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3.5 shadow-sm">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-100">
                <i data-lucide="x-circle" class="h-4 w-4 text-rose-600"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-semibold text-rose-800">
                    {{ $latestRequest->typeLabel() }} Rejected by {{ $latestRequest->reviewedBy->name ?? 'CFO' }}
                    <span class="font-normal text-rose-500">· {{ $latestRequest->reviewed_at?->diffForHumans() }}</span>
                </p>
                <p class="mt-1 text-[12.5px] italic text-rose-700">
                    "{{ $latestRequest->review_note ?: 'No reason was provided.' }}"
                </p>
                @if ($latestRequest->isCreate())
                <p class="mt-1.5 text-[11.5px] text-rose-500">This voucher was not approved. It stays on record for reference only.</p>
                @elseif ($latestRequest->isEdit())
                <p class="mt-1.5 text-[11.5px] text-rose-500">The voucher keeps its original, already-approved values. You can submit another edit request.</p>
                @else
                <p class="mt-1.5 text-[11.5px] text-rose-500">The voucher remains active and unchanged. You can submit another delete request.</p>
                @endif
            </div>
        </div>
        @endif
    @endif

    {{-- ── Check voucher document ──────────────────────────────────────── --}}
    @include('vouchers.partials.check-voucher-document')

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
    </div>

    {{-- ── Attachments ──────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
            <i data-lucide="paperclip" class="h-3.5 w-3.5"></i> Attachments
            <span class="font-normal normal-case text-slate-400">({{ $voucher->attachments->count() }})</span>
        </h3>

        @if ($voucher->attachments->isNotEmpty())
            <ul class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
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
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-[12px] text-slate-400">No supporting documents attached yet.</p>
        @endif
    </div>

</div>
</x-app-layout>
