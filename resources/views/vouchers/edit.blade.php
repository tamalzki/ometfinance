<x-app-layout page-title="Edit Voucher">
@php
    $oldEntries = old('entries', []);

    $voucherEntries = $voucher->entries->map(fn ($e) => [
        'id'          => $e->id,
        'category_id' => (string) ($e->category_id ?? ''),
        'entry_type'  => $e->entry_type,
        'amount'      => number_format((float) $e->amount, 2, '.', ''),
        'project_id'  => (string) ($e->project_id ?? ''),
        'description' => $e->description ?? '',
    ])->values()->toArray();

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

    $sourceDocumentsForPicker = collect($sourceDocuments)->map(fn ($label, $key) => [
        'id'          => $key,
        'label'       => $label,
        'icon'        => $sourceDocumentIcons[$key] ?? 'file-question',
        'numberLabel' => $sourceDocumentNumberLabels[$key] ?? 'Reference Number',
    ])->values();

    $oldPayee = old('payee_name', $voucher->payee_name);
    $payeeOtherInitial = $oldPayee !== '' && ! $payees->contains($oldPayee);
@endphp

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('createVoucherPage', () => ({
        projects:   @json($projectsForPicker),
        accounts:   @json($accountsForPicker),
        types:      @json($typesForPicker),
        modes:      @json($modesForPicker),
        payees:     @json($payeesForPicker),
        categories: @json($categoriesForPicker),
        sourceDocuments: @json($sourceDocumentsForPicker),

        acctOpen: false,  acctQuery: '',
        typeOpen: false,  typeQuery: '',
        modeOpen: false,  modeQuery: '',
        payeeOpen: false, payeeQuery: '',

        payeeOther: @json($payeeOtherInitial),

        f: {
            voucher_no:             @json(old('voucher_no', $voucher->voucher_no)),
            voucher_date:           @json(old('voucher_date', $voucher->voucher_date?->format('Y-m-d'))),
            due_date:               @json(old('due_date', $voucher->due_date?->format('Y-m-d') ?? '')),
            release_date:           @json(old('release_date', $voucher->release_date?->format('Y-m-d') ?? '')),
            payee_name:             @json(old('payee_name', $voucher->payee_name)),
            source:                 @json(old('source', $voucher->source ?? $defaultSource)),
            source_bank_account_id: @json(old('source_bank_account_id', (string) ($voucher->source_bank_account_id ?? ''))),
            transaction_type:       @json(old('transaction_type', $voucher->transaction_type ?? 'rfp')),
            source_document_type:   @json(old('source_document_type', $voucher->source_document_type ?? '')),
            po_number:              @json(old('po_number', $voucher->po_number ?? '')),
            reference:              @json(old('reference', $voucher->reference ?? '')),
            mode_of_payment:        @json(old('mode_of_payment', $voucher->mode_of_payment ?? 'cash')),
            particular:             @json(old('particular', $voucher->particular ?? '')),
            remarks:                @json(old('remarks', $voucher->remarks ?? '')),
            source_of_fund:         @json(old('source_of_fund', $voucher->source_of_fund ?? '')),
            or_ref:                 @json(old('or_ref', $voucher->or_ref ?? '')),
            change_amount:          @json(old('change_amount', $voucher->change_amount ?? '')),
            notes:                  @json(old('notes', $voucher->notes ?? '')),
            payment_status:         @json(old('payment_status', $voucher->status === 'paid' ? 'paid' : 'unpaid')),
        },

        entries: [],

        init() {
            const fallback = @json($voucherEntries);
            const restored = @json(array_values($oldEntries));
            const seed = restored.length > 0 ? restored : fallback;
            this.entries = seed.map(e => ({
                _catOpen: false, _catQ: '', _projOpen: false, _projQ: '',
                ...e
            }));
        },

        get totalDebit() {
            return this.entries.filter(e => e.entry_type === 'debit').reduce((s, e) => s + (parseFloat(e.amount) || 0), 0);
        },
        get totalCredit() {
            return this.entries.filter(e => e.entry_type === 'credit').reduce((s, e) => s + (parseFloat(e.amount) || 0), 0);
        },
        get isBalanced() {
            if (this.entries.length === 0) return false;
            return Math.abs(this.totalDebit - this.totalCredit) < 0.005;
        },
        get netAmount() {
            return this.isBalanced ? this.totalDebit : null;
        },
        // Amount actually disbursed — the "Cash in Bank" credit line, not the
        // full debit total, since other credits (e.g. WHT) are withheld, not paid out.
        get amountPayable() {
            const cash = this.entries
                .filter(e => e.entry_type === 'credit' && this.catLabel(e).toLowerCase().includes('cash in bank'))
                .reduce((s, e) => s + (parseFloat(e.amount) || 0), 0);
            return cash > 0 ? cash : this.totalDebit;
        },

        addEntry(type) {
            this.entries.push({
                _catOpen: false, _catQ: '',
                _projOpen: false, _projQ: '',
                category_id: '', entry_type: type, amount: '', project_id: '', description: '',
            });
        },
        removeEntry(idx) {
            this.entries.splice(idx, 1);
        },
        catLabel(entry) {
            if (! entry.category_id) return '';
            const c = this.categories.find(x => String(x.id) === String(entry.category_id));
            return c ? c.label : '';
        },
        projLabel(entry) {
            if (! entry.project_id) return '';
            const p = this.projects.find(x => String(x.id) === String(entry.project_id));
            return p ? p.label : '';
        },
        formatPeso(n) {
            return '₱' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        filteredOptions(list, query) {
            const needle = (query || '').trim().toLowerCase();
            if (! needle) return list;
            return list.filter(o => (o.search || o.label || '').toLowerCase().includes(needle));
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
        docLabel(id) {
            const d = this.sourceDocuments.find(x => x.id === id);
            return d ? d.label : '';
        },
        docNumberLabel(id) {
            const d = this.sourceDocuments.find(x => x.id === id);
            return d ? d.numberLabel : 'Reference Number';
        },

        entryError: '',
        validateAndSubmit(form) {
            this.entryError = '';
            if (this.entries.length === 0) {
                this.entryError = 'Accounting entries are required. Add at least one Debit and one Credit row.';
                this.$nextTick(() => document.getElementById('entry-error-banner')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                return;
            }
            const missingCat = this.entries.some(e => ! e.category_id);
            if (missingCat) {
                this.entryError = 'All accounting entries must have a category selected.';
                this.$nextTick(() => document.getElementById('entry-error-banner')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                return;
            }
            if (! this.isBalanced) {
                this.entryError = 'Entries are out of balance — Total Debit must equal Total Credit before saving.';
                this.$nextTick(() => document.getElementById('entry-error-banner')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                return;
            }
            form.dispatchEvent(new Event('form:submitting'));
            form.submit();
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
        keptAttachments: @json($pendingAttachments ?? []),
        removeKept(token) {
            this.keptAttachments = this.keptAttachments.filter(a => a.token !== token);
        },
    }));
});
</script>

<x-unsaved-changes-guard selector="#voucherForm" />

<div class="mx-auto max-w-4xl px-4 py-8" x-data="createVoucherPage">

    {{-- Page header --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <p class="text-[10.5px] font-semibold uppercase tracking-wider text-omet-blue">Disbursement</p>
            <div class="mt-0.5 flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-omet-navy">Edit Voucher</h1>
                <template x-if="f.source_document_type">
                    <span class="inline-flex items-center gap-1 rounded-full bg-omet-blue/10 px-2.5 py-1 text-[11px] font-semibold text-omet-blue">
                        <i data-lucide="tag" class="h-3 w-3"></i>
                        <span x-text="docLabel(f.source_document_type)"></span>
                    </span>
                </template>
            </div>
            <p class="mt-0.5 text-xs text-slate-500">{{ $voucher->voucher_no }} — {{ $voucher->payee_name }}</p>
        </div>
        <a href="{{ route('vouchers.show', $voucher) }}"
           class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 py-2 text-[12.5px] font-medium text-slate-600 shadow-sm transition hover:bg-slate-50">
            ← Back to Voucher
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4">
            <p class="mb-1.5 text-[12px] font-semibold text-red-700">Please fix the following errors:</p>
            <ul class="list-inside list-disc space-y-0.5 text-[12px] text-red-600">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="voucherForm" method="POST" action="{{ route('vouchers.update', $voucher) }}" enctype="multipart/form-data"
          @submit.prevent="validateAndSubmit($el)">
        @csrf
        @method('PUT')
        <input type="hidden" name="amount_payable" :value="amountPayable > 0 ? amountPayable.toFixed(2) : ''">

        {{-- ══════════════════════════════════════════════════════════════
             CARD 0 — Source Document for Voucher
        ══════════════════════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-slate-200 border-t-4 border-t-amber-400 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-6 py-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-amber-50">
                    <i data-lucide="tag" class="h-3 w-3 text-amber-600"></i>
                </span>
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Source Document for Voucher</h2>
            </div>
            <div class="flex flex-wrap items-center gap-3 px-6 py-3">
                <input type="hidden" name="source_document_type" :value="f.source_document_type">
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($sourceDocuments as $key => $label)
                        <button type="button" @click="f.source_document_type = '{{ $key }}'"
                                class="flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[12px] font-medium transition"
                                :class="f.source_document_type === '{{ $key }}' ? 'border-omet-blue bg-omet-blue/5 text-omet-blue' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'">
                            <i data-lucide="{{ $sourceDocumentIcons[$key] ?? 'file-question' }}" class="h-3.5 w-3.5"></i>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
                <div x-show="!!f.source_document_type" x-cloak class="flex items-center gap-2 border-l border-slate-200 pl-3">
                    <label class="shrink-0 text-[11px] font-medium text-gray-600">
                        <span x-text="docNumberLabel(f.source_document_type)"></span> <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="po_number" x-model="f.po_number" placeholder="Number"
                           :required="!!f.source_document_type"
                           class="h-8 w-36 rounded-lg border border-slate-200 bg-white px-2.5 text-[12.5px] text-gray-800 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                </div>
            </div>
            @error('source_document_type')<p class="px-6 pb-2.5 -mt-1 text-[10.5px] text-red-600">{{ $message }}</p>@enderror
            @error('po_number')<p class="px-6 pb-2.5 -mt-1 text-[10.5px] text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- ══════════════════════════════════════════════════════════════
             CARD 1 — Voucher Information
        ══════════════════════════════════════════════════════════════ --}}
        <div class="mt-5 rounded-xl border border-slate-200 border-t-4 border-t-omet-blue bg-white shadow-sm">

            <div class="flex items-center gap-2.5 border-b border-slate-100 px-6 py-4">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-omet-blue/10">
                    <i data-lucide="file-text" class="h-3.5 w-3.5 text-omet-blue"></i>
                </span>
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Voucher Information</h2>
            </div>

            <div class="space-y-6 px-6 py-5">

                {{-- Reference & Dates --}}
                <div>
                    <p class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-slate-400">Reference &amp; Dates</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-gray-600">Voucher No. *</label>
                            <input type="text" name="voucher_no" required x-model="f.voucher_no"
                                   placeholder="e.g. 2026-0001"
                                   class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10 @error('voucher_no') border-red-400 @enderror">
                            @error('voucher_no')<p class="mt-0.5 text-[10.5px] text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-gray-600">Voucher Date *</label>
                            <input type="date" name="voucher_date" required x-model="f.voucher_date"
                                   class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-gray-600">Due Date <span class="text-gray-400">(payable)</span></label>
                            <input type="date" name="due_date" x-model="f.due_date"
                                   class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-gray-600">Release Date <span class="text-gray-400">(actual)</span></label>
                            <input type="date" name="release_date" x-model="f.release_date"
                                   class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="mb-1.5 block text-[11px] font-medium text-gray-600">Voucher Source</label>
                        @if ($lockedSource ?? null)
                            <input type="hidden" name="source" value="{{ $lockedSource }}">
                            <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-[12.5px] font-medium text-slate-500">
                                <i data-lucide="lock" class="h-3.5 w-3.5"></i>
                                {{ $sources[$lockedSource] ?? $lockedSource }}
                                <span class="text-[11px] font-normal text-slate-400">— locked to your office</span>
                            </div>
                        @else
                        <div class="flex flex-wrap gap-2.5">
                            @foreach ($sources as $key => $label)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2.5 text-[12.5px] font-medium transition"
                                       :class="f.source === '{{ $key }}' ? 'border-omet-blue bg-omet-blue/5 text-omet-blue' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'">
                                    <input type="radio" name="source" value="{{ $key }}" x-model="f.source" class="sr-only">
                                    <span class="flex h-4 w-4 items-center justify-center rounded-full border-2 transition"
                                          :class="f.source === '{{ $key }}' ? 'border-omet-blue' : 'border-slate-300'">
                                        <span class="h-2 w-2 rounded-full bg-omet-blue transition"
                                              :class="f.source === '{{ $key }}' ? 'opacity-100' : 'opacity-0'"></span>
                                    </span>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>

                <div class="border-t border-slate-100"></div>

                {{-- Payment Details --}}
                <div>
                    <p class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-slate-400">Payment Details</p>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                        {{-- Payee combobox --}}
                        <div class="relative" @click.outside="payeeOpen = false">
                            <label class="mb-1 block text-[11px] font-medium text-gray-600">Payee *</label>
                            <template x-if="!payeeOther">
                                <div>
                                    <button type="button"
                                            @click="payeeOpen = !payeeOpen; if (payeeOpen) $nextTick(() => $refs.payeeSearch?.focus())"
                                            class="flex h-10 w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 text-left text-[13px] text-gray-800 outline-none transition hover:border-slate-300 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                                        <span class="truncate" x-text="f.payee_name || '— select payee —'"></span>
                                        <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-gray-400"></i>
                                    </button>
                                    <input type="hidden" name="payee_name" :value="f.payee_name" required>
                                    <div x-show="payeeOpen" x-cloak
                                         class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                                        <div class="border-b border-slate-100 p-2">
                                            <input x-ref="payeeSearch" x-model="payeeQuery" type="text" placeholder="Search payee…"
                                                   class="h-8 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[12px] outline-none focus:border-omet-blue focus:bg-white">
                                        </div>
                                        <div class="max-h-48 overflow-y-auto py-1">
                                            <template x-for="p in filteredOptions(payees, payeeQuery)" :key="p.id">
                                                <button type="button" @click="f.payee_name = p.label; payeeOpen = false; payeeQuery = ''"
                                                        class="flex w-full px-3 py-2 text-left text-[12px] hover:bg-blue-50"
                                                        :class="p.label === f.payee_name ? 'bg-blue-50/60 font-medium text-omet-blue' : 'text-gray-700'"
                                                        x-text="p.label"></button>
                                            </template>
                                            <p x-show="filteredOptions(payees, payeeQuery).length === 0"
                                               class="px-3 py-3 text-center text-[11px] text-gray-400">No payees match.</p>
                                        </div>
                                        <div class="border-t border-slate-100 p-1">
                                            <button type="button"
                                                    @click="payeeOther = true; f.payee_name = ''; payeeOpen = false; payeeQuery = ''; $nextTick(() => $refs.payeeOtherInput?.focus())"
                                                    class="flex w-full items-center gap-1.5 rounded-md px-3 py-2 text-left text-[12px] font-medium text-omet-blue hover:bg-blue-50">
                                                <i data-lucide="plus" class="h-3.5 w-3.5"></i> Other — type manually
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="payeeOther">
                                <div class="flex items-stretch gap-1">
                                    <input x-ref="payeeOtherInput" type="text" name="payee_name" required x-model="f.payee_name"
                                           placeholder="Type payee name"
                                           class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                                    <button type="button" @click="payeeOther = false; f.payee_name = ''"
                                            class="h-10 shrink-0 rounded-lg border border-slate-200 bg-white px-3 text-[11px] text-gray-500 transition hover:bg-slate-50">List</button>
                                </div>
                            </template>
                            @error('payee_name')<p class="mt-0.5 text-[10.5px] text-red-600">{{ $message }}</p>@enderror
                        </div>

                        {{-- Source bank account combobox --}}
                        <div class="relative" @click.outside="acctOpen = false">
                            <label class="mb-1 block text-[11px] font-medium text-gray-600">Source Bank Account <span class="text-gray-400">(money out)</span></label>
                            <div class="flex items-stretch gap-1">
                                <button type="button" @click="acctOpen = !acctOpen; if (acctOpen) $nextTick(() => $refs.acctSearch?.focus())"
                                        class="flex h-10 flex-1 items-center justify-between rounded-lg border border-slate-200 bg-white px-3 text-left text-[13px] text-gray-800 outline-none transition hover:border-slate-300 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                                    <span class="truncate" x-text="accountLabel(f.source_bank_account_id)"></span>
                                    <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-gray-400"></i>
                                </button>
                                <button type="button" x-show="f.source_bank_account_id" @click.stop="f.source_bank_account_id = ''"
                                        class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-[11px] text-gray-500 transition hover:bg-rose-50 hover:text-rose-600">Clear</button>
                            </div>
                            <input type="hidden" name="source_bank_account_id" :value="f.source_bank_account_id">
                            <div x-show="acctOpen" x-cloak
                                 class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                                <div class="border-b border-slate-100 p-2">
                                    <input x-ref="acctSearch" x-model="acctQuery" type="text" placeholder="Search bank or entity…"
                                           class="h-8 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[12px] outline-none focus:border-omet-blue focus:bg-white">
                                </div>
                                <div class="max-h-56 overflow-y-auto py-1">
                                    <button type="button" @click="f.source_bank_account_id = ''; acctOpen = false; acctQuery = ''"
                                            class="flex w-full px-3 py-2 text-left text-[12px] text-slate-500 hover:bg-slate-50">Pending — source not yet confirmed</button>
                                    <template x-for="a in filteredOptions(accounts, acctQuery)" :key="a.id">
                                        <button type="button" @click="f.source_bank_account_id = String(a.id); acctOpen = false; acctQuery = ''"
                                                class="flex w-full px-3 py-2 text-left text-[12px] hover:bg-blue-50"
                                                :class="String(a.id) === String(f.source_bank_account_id) ? 'bg-blue-50/60 font-medium text-omet-blue' : 'text-gray-700'">
                                            <span class="block truncate" x-text="a.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

                        {{-- Transaction type --}}
                        <div class="relative" @click.outside="typeOpen = false">
                            <label class="mb-1 block text-[11px] font-medium text-gray-600">Transaction Type</label>
                            <button type="button" @click="typeOpen = !typeOpen; if (typeOpen) $nextTick(() => $refs.typeSearch?.focus())"
                                    class="flex h-10 w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 text-left text-[13px] text-gray-800 outline-none transition hover:border-slate-300 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                                <span class="truncate" x-text="typeLabel(f.transaction_type)"></span>
                                <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-gray-400"></i>
                            </button>
                            <input type="hidden" name="transaction_type" :value="f.transaction_type">
                            <div x-show="typeOpen" x-cloak
                                 class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                                <div class="border-b border-slate-100 p-2">
                                    <input x-ref="typeSearch" x-model="typeQuery" type="text" placeholder="Search type…"
                                           class="h-8 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[12px] outline-none focus:border-omet-blue focus:bg-white">
                                </div>
                                <div class="max-h-56 overflow-y-auto py-1">
                                    <template x-for="t in filteredOptions(types, typeQuery)" :key="t.id">
                                        <button type="button" @click="f.transaction_type = t.id; typeOpen = false; typeQuery = ''"
                                                class="flex w-full px-3 py-2 text-left text-[12px] hover:bg-blue-50"
                                                :class="f.transaction_type === t.id ? 'bg-blue-50/60 font-medium text-omet-blue' : 'text-gray-700'"
                                                x-text="t.label"></button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Mode of payment --}}
                        <div class="relative" @click.outside="modeOpen = false">
                            <label class="mb-1 block text-[11px] font-medium text-gray-600">Mode of Payment</label>
                            <button type="button" @click="modeOpen = !modeOpen; if (modeOpen) $nextTick(() => $refs.modeSearch?.focus())"
                                    class="flex h-10 w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 text-left text-[13px] text-gray-800 outline-none transition hover:border-slate-300 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                                <span class="truncate" x-text="modeLabel(f.mode_of_payment)"></span>
                                <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-gray-400"></i>
                            </button>
                            <input type="hidden" name="mode_of_payment" :value="f.mode_of_payment">
                            <div x-show="modeOpen" x-cloak
                                 class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                                <div class="border-b border-slate-100 p-2">
                                    <input x-ref="modeSearch" x-model="modeQuery" type="text" placeholder="Search mode…"
                                           class="h-8 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[12px] outline-none focus:border-omet-blue focus:bg-white">
                                </div>
                                <div class="max-h-56 overflow-y-auto py-1">
                                    <template x-for="m in filteredOptions(modes, modeQuery)" :key="m.id">
                                        <button type="button" @click="f.mode_of_payment = m.id; modeOpen = false; modeQuery = ''"
                                                class="flex w-full px-3 py-2 text-left text-[12px] hover:bg-blue-50"
                                                :class="f.mode_of_payment === m.id ? 'bg-blue-50/60 font-medium text-omet-blue' : 'text-gray-700'"
                                                x-text="m.label"></button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Payment status --}}
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-gray-600">Payment Status</label>
                            <select name="payment_status" x-model="f.payment_status"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                                <option value="unpaid">Unpaid</option>
                                <option value="paid">Paid</option>
                            </select>
                            <template x-if="f.payment_status === 'paid' && !f.source_bank_account_id">
                                <p class="mt-1 text-[10.5px] font-medium text-amber-700">Set a source bank account — required to post the payment.</p>
                            </template>
                        </div>
                    </div>

                    <div class="mt-4 max-w-sm">
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Reference <span class="text-gray-400">(PR / OR / SI)</span></label>
                        <input type="text" name="reference" x-model="f.reference"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                </div>

                <div class="border-t border-slate-100"></div>

                {{-- Particulars & Notes --}}
                <div>
                    <p class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-slate-400">Particulars &amp; Notes</p>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-gray-600">Particular / Description</label>
                            <textarea name="particular" rows="2" x-model="f.particular"
                                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-[12.5px] text-gray-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10"></textarea>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-gray-600">Remarks <span class="text-gray-400">(col O)</span></label>
                                <textarea name="remarks" rows="2" x-model="f.remarks" placeholder="e.g. UNLIQUIDATED, Check deposit…"
                                          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-[12.5px] text-gray-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10"></textarea>
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-gray-600">Source of Fund <span class="text-gray-400">(col T)</span></label>
                                <textarea name="source_of_fund" rows="2" x-model="f.source_of_fund"
                                          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-[12.5px] text-gray-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10"></textarea>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-gray-600">OR / CR / SI / CI Ref.</label>
                                <input type="text" name="or_ref" x-model="f.or_ref"
                                       class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-gray-600">Change / Excess Returned</label>
                                <input type="number" step="0.01" min="0" name="change_amount" x-model="f.change_amount" placeholder="0.00"
                                       class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-right text-[13px] tabular-nums text-gray-800 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium text-gray-600">Notes <span class="text-gray-400">(internal)</span></label>
                                <input type="text" name="notes" x-model="f.notes"
                                       class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100"></div>

            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════
             CARD 2 — Accounting Entries
        ══════════════════════════════════════════════════════════════ --}}
        <div class="mt-5 rounded-xl border border-slate-200 border-t-4 border-t-indigo-400 bg-white shadow-sm">

            {{-- Card header --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                <div class="flex items-start gap-2.5">
                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
                        <i data-lucide="scale" class="h-3.5 w-3.5 text-indigo-600"></i>
                    </span>
                    <div>
                        <h2 class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                            Accounting Entries
                            <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-600">Required</span>
                        </h2>
                        <p class="mt-0.5 text-[10.5px] text-slate-400">Total Debit must equal Total Credit before saving.</p>
                    </div>
                </div>
                {{-- + Debit / + Credit buttons --}}
                <div class="flex items-center gap-2">
                    <button type="button" @click="addEntry('debit')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-2 text-[12px] font-semibold text-blue-700 transition hover:bg-blue-100">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i> Debit
                    </button>
                    <button type="button" @click="addEntry('credit')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-[12px] font-semibold text-emerald-700 transition hover:bg-emerald-100">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i> Credit
                    </button>
                </div>
            </div>

            <div class="px-6 py-5">

                @error('entries')
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-[12px] font-medium text-red-700">{{ $message }}</div>
                @enderror

                <div id="entry-error-banner" x-show="entryError" x-cloak
                     class="mb-4 flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                    <i data-lucide="alert-circle" class="mt-0.5 h-4 w-4 shrink-0 text-red-500"></i>
                    <p class="text-[12px] font-medium text-red-700" x-text="entryError"></p>
                </div>

                {{-- Empty state --}}
                <template x-if="entries.length === 0">
                    <div class="rounded-lg border border-dashed border-slate-200 py-10 text-center text-[12px] text-slate-400">
                        <i data-lucide="git-branch-plus" class="mx-auto mb-2 h-6 w-6 text-slate-300"></i>
                        Click <span class="font-semibold text-blue-600">+ Debit</span> or <span class="font-semibold text-emerald-600">+ Credit</span> to add accounting entries
                    </div>
                </template>

                {{-- Entry rows --}}
                <template x-if="entries.length > 0">
                    <div class="space-y-2">
                        <template x-for="(entry, idx) in entries" :key="idx">
                            <div class="flex flex-wrap items-center gap-2 rounded-lg border px-3 py-2.5 transition"
                                 :class="entry.entry_type === 'debit'
                                     ? 'border-blue-100 bg-blue-50/40'
                                     : 'border-emerald-100 bg-emerald-50/30'">

                                {{-- DR / CR toggle --}}
                                <div class="flex shrink-0 overflow-hidden rounded-lg border"
                                     :class="entry.entry_type === 'debit' ? 'border-blue-200' : 'border-emerald-200'">
                                    <button type="button" @click="entry.entry_type = 'debit'"
                                            class="px-3 py-1.5 text-[11.5px] font-bold transition"
                                            :class="entry.entry_type === 'debit' ? 'bg-blue-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-50'">
                                        DR
                                    </button>
                                    <button type="button" @click="entry.entry_type = 'credit'"
                                            class="border-l px-3 py-1.5 text-[11.5px] font-bold transition"
                                            :class="entry.entry_type === 'credit' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50'">
                                        CR
                                    </button>
                                </div>
                                <input type="hidden" :name="`entries[${idx}][entry_type]`" :value="entry.entry_type">
                                <input type="hidden" :name="`entries[${idx}][id]`" :value="entry.id">

                                {{-- Category combobox --}}
                                <div class="relative flex-[2] min-w-[180px]" @click.outside="entry._catOpen = false">
                                    <button type="button"
                                            @click="entry._catOpen = !entry._catOpen"
                                            class="flex h-9 w-full items-center justify-between rounded-lg border bg-white px-2.5 text-left text-[12px] outline-none transition"
                                            :class="entry.category_id ? 'border-slate-200 text-gray-800' : 'border-red-200 text-slate-400'">
                                        <span class="truncate" x-text="catLabel(entry) || '— Category * —'"></span>
                                        <i data-lucide="chevron-down" class="ml-1 h-3.5 w-3.5 shrink-0 text-gray-400"></i>
                                    </button>
                                    <input type="hidden" :name="`entries[${idx}][category_id]`" :value="entry.category_id">
                                    <div x-show="entry._catOpen" x-cloak
                                         class="absolute left-0 z-50 mt-0.5 w-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                                        <div class="border-b border-slate-100 p-1.5">
                                            <input x-model="entry._catQ" type="text" placeholder="Search category…"
                                                   @click.stop
                                                   class="h-7 w-full rounded border border-slate-200 bg-slate-50 px-2 text-[11.5px] outline-none focus:border-omet-blue focus:bg-white">
                                        </div>
                                        <div class="max-h-48 overflow-y-auto py-0.5">
                                            <template x-for="cat in filteredOptions(categories, entry._catQ)" :key="cat.id">
                                                <button type="button"
                                                        @click="entry.category_id = String(cat.id); entry._catOpen = false; entry._catQ = ''"
                                                        class="flex w-full px-2.5 py-1.5 text-left text-[11.5px] hover:bg-blue-50"
                                                        :class="String(cat.id) === String(entry.category_id) ? 'bg-blue-50 font-medium text-omet-blue' : 'text-gray-700'"
                                                        x-text="cat.label">
                                                </button>
                                            </template>
                                            <p x-show="filteredOptions(categories, entry._catQ).length === 0"
                                               class="px-2.5 py-3 text-center text-[11px] text-slate-400">No match</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Amount --}}
                                <div class="relative w-[120px] shrink-0">
                                    <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[12px] text-slate-400">₱</span>
                                    <input type="number" step="0.01" min="0.01"
                                           :name="`entries[${idx}][amount]`"
                                           x-model="entry.amount" required placeholder="0.00"
                                           class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-6 pr-2 text-right text-[12.5px] tabular-nums text-gray-800 outline-none focus:border-omet-blue focus:ring-1 focus:ring-omet-blue/10">
                                </div>

                                {{-- Project combobox --}}
                                <div class="relative flex-[2] min-w-[160px]" @click.outside="entry._projOpen = false">
                                    <button type="button"
                                            @click="entry._projOpen = !entry._projOpen"
                                            class="flex h-9 w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-2.5 text-left text-[12px] text-gray-700 outline-none transition hover:border-slate-300">
                                        <span class="truncate"
                                              :class="entry.project_id ? 'text-gray-800' : 'text-slate-400'"
                                              x-text="projLabel(entry) || '— Project (opt.) —'"></span>
                                        <i data-lucide="chevron-down" class="ml-1 h-3.5 w-3.5 shrink-0 text-gray-400"></i>
                                    </button>
                                    <input type="hidden" :name="`entries[${idx}][project_id]`" :value="entry.project_id">
                                    <div x-show="entry._projOpen" x-cloak
                                         class="absolute left-0 z-50 mt-0.5 w-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                                        <div class="border-b border-slate-100 p-1.5">
                                            <input x-model="entry._projQ" type="text" placeholder="Search project…"
                                                   @click.stop
                                                   class="h-7 w-full rounded border border-slate-200 bg-slate-50 px-2 text-[11.5px] outline-none focus:border-omet-blue focus:bg-white">
                                        </div>
                                        <div class="max-h-48 overflow-y-auto py-0.5">
                                            <button type="button"
                                                    @click="entry.project_id = ''; entry._projOpen = false; entry._projQ = ''"
                                                    class="flex w-full px-2.5 py-1.5 text-left text-[11px] text-slate-500 hover:bg-slate-50">— none —</button>
                                            <template x-for="proj in filteredOptions(projects, entry._projQ)" :key="proj.id">
                                                <button type="button"
                                                        @click="entry.project_id = String(proj.id); entry._projOpen = false; entry._projQ = ''"
                                                        class="flex w-full flex-col px-2.5 py-1.5 text-left hover:bg-blue-50"
                                                        :class="String(proj.id) === String(entry.project_id) ? 'bg-blue-50' : ''">
                                                    <span class="text-[11.5px] font-medium"
                                                          :class="String(proj.id) === String(entry.project_id) ? 'text-omet-blue' : 'text-gray-800'"
                                                          x-text="proj.label"></span>
                                                    <span class="text-[10px] text-slate-400" x-text="proj.kind"></span>
                                                </button>
                                            </template>
                                            <p x-show="filteredOptions(projects, entry._projQ).length === 0"
                                               class="px-2.5 py-3 text-center text-[11px] text-slate-400">No match</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Description --}}
                                <input type="text" :name="`entries[${idx}][description]`"
                                       x-model="entry.description" placeholder="Description (optional)"
                                       class="h-9 flex-[3] min-w-[130px] rounded-lg border border-slate-200 bg-white px-2.5 text-[12px] text-gray-700 outline-none focus:border-omet-blue focus:ring-1 focus:ring-omet-blue/10">

                                {{-- Remove --}}
                                <button type="button" @click="removeEntry(idx)"
                                        class="ml-auto shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600">
                                    <i data-lucide="trash-2" class="h-4 w-4 pointer-events-none"></i>
                                </button>
                            </div>
                        </template>

                        {{-- Totals + Net Amount bar --}}
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 px-5 py-3">
                            <div class="flex flex-wrap items-center gap-5">
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] font-semibold uppercase tracking-wide text-blue-700">Total Debit</span>
                                    <span class="text-[13.5px] font-bold tabular-nums text-blue-900" x-text="formatPeso(totalDebit)"></span>
                                </div>
                                <div class="h-4 w-px bg-slate-300"></div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Total Credit</span>
                                    <span class="text-[13.5px] font-bold tabular-nums text-emerald-900" x-text="formatPeso(totalCredit)"></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                {{-- Balance indicator --}}
                                <span class="text-[11.5px] font-medium"
                                      :class="isBalanced ? 'text-emerald-700' : 'text-amber-700'">
                                    <template x-if="isBalanced">
                                        <span class="inline-flex items-center gap-1.5">
                                            <i data-lucide="check-circle-2" class="h-3.5 w-3.5"></i> Balanced
                                        </span>
                                    </template>
                                    <template x-if="!isBalanced">
                                        <span class="inline-flex items-center gap-1.5">
                                            <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i>
                                            Off by <span class="font-bold tabular-nums" x-text="formatPeso(Math.abs(totalDebit - totalCredit))"></span>
                                        </span>
                                    </template>
                                </span>
                                {{-- Net Amount (only when balanced) --}}
                                <template x-if="isBalanced">
                                    <div class="flex items-center gap-2 rounded-lg border border-omet-blue/20 bg-omet-blue/5 px-3 py-1.5">
                                        <span class="text-[10.5px] font-semibold uppercase tracking-wide text-omet-blue">Net Amount</span>
                                        <span class="text-[14px] font-bold tabular-nums text-omet-navy" x-text="formatPeso(netAmount)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════
             CARD 3 — Attachments
        ══════════════════════════════════════════════════════════════ --}}
        <div class="mt-5 rounded-xl border border-slate-200 border-t-4 border-t-slate-300 bg-white shadow-sm">
            <div class="flex items-center gap-2.5 border-b border-slate-100 px-6 py-4">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100">
                    <i data-lucide="paperclip" class="h-3.5 w-3.5 text-slate-500"></i>
                </span>
                @php $hasExistingAttachments = $voucher->attachments->isNotEmpty(); @endphp
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    Attachments
                    @if ($hasExistingAttachments)
                        <span class="ml-1 font-normal normal-case text-slate-400">(optional — already has {{ $voucher->attachments->count() }})</span>
                    @else
                        <span class="ml-1 font-normal normal-case text-red-500">(required)</span>
                    @endif
                </h2>
            </div>
            <div class="px-6 py-5">
                @if ($hasExistingAttachments)
                <ul class="mb-3 divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
                    @foreach ($voucher->attachments as $a)
                    <li class="flex items-center justify-between gap-3 px-3 py-2 text-[12px]">
                        <a href="{{ route('vouchers.attachments.download', $a) }}" class="flex min-w-0 items-center gap-2 text-slate-700 hover:text-omet-blue hover:underline">
                            <i data-lucide="file-text" class="h-3.5 w-3.5 shrink-0 text-slate-400"></i>
                            <span class="truncate">{{ $a->original_name }}</span>
                        </a>
                        <span class="shrink-0 text-[10.5px] text-slate-400">{{ $a->humanSize() }}</span>
                    </li>
                    @endforeach
                </ul>
                @endif
                <template x-if="keptAttachments.length">
                    <ul class="mb-3 divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
                        <template x-for="kept in keptAttachments" :key="kept.token">
                            <li class="flex items-center justify-between gap-3 px-3 py-2 text-[12px]">
                                <input type="hidden" name="kept_attachment_tokens[]" :value="kept.token">
                                <span class="flex min-w-0 items-center gap-2 text-slate-700">
                                    <i data-lucide="file-text" class="h-3.5 w-3.5 shrink-0 text-slate-400"></i>
                                    <span class="truncate" x-text="kept.name"></span>
                                </span>
                                <button type="button" @click="removeKept(kept.token)" class="shrink-0 text-slate-400 transition hover:text-red-500" title="Remove">
                                    <i data-lucide="x" class="h-3.5 w-3.5"></i>
                                </button>
                            </li>
                        </template>
                    </ul>
                </template>
                <p class="mb-3 text-[10.5px] text-gray-400">PDF, images, Word or Excel · max 10 MB each{{ $hasExistingAttachments ? '' : ' · at least one file is required' }}</p>
                <input type="file" name="attachments[]" multiple :required="{{ $hasExistingAttachments ? 'false' : 'keptAttachments.length === 0' }}"
                       accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                       @change="validateAttachments($event.target)"
                       class="block w-full cursor-pointer text-[12px] text-slate-600 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-omet-blue file:px-3 file:py-1.5 file:text-[11px] file:font-semibold file:text-white hover:file:bg-omet-lightblue">
                <p x-show="attachmentError" x-cloak class="mt-1.5 text-[11px] font-medium text-red-600">
                    <span x-text="attachmentError"></span> exceeds the 10 MB limit.
                </p>
                @error('attachments')<p class="mt-1.5 text-[11px] font-medium text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Submit bar --}}
        <div class="mt-6 space-y-3">

            {{-- Amount Payable — emphasized, auto-calculated --}}
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border-2 border-omet-blue/20 bg-omet-blue/5 px-6 py-4">
                <div>
                    <p class="text-[10.5px] font-semibold uppercase tracking-wide text-omet-blue">Amount Payable</p>
                    <p class="mt-0.5 text-[10.5px] text-slate-500">Auto-calculated from the accounting entries above</p>
                    @error('amount_payable')<p class="mt-0.5 text-[10.5px] font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
                <p class="text-2xl font-bold tabular-nums text-omet-navy" x-text="formatPeso(amountPayable)"></p>
            </div>

            {{-- Contextual hint why button is disabled --}}
            <template x-if="entries.length === 0">
                <p class="text-right text-[11.5px] font-medium text-amber-700">
                    <i data-lucide="info" class="inline h-3.5 w-3.5 align-middle"></i>
                    Add accounting entries (Debit &amp; Credit) before saving.
                </p>
            </template>
            <template x-if="entries.length > 0 && !isBalanced">
                <p class="text-right text-[11.5px] font-medium text-amber-700">
                    <i data-lucide="info" class="inline h-3.5 w-3.5 align-middle"></i>
                    Debit and Credit totals must be equal before saving.
                    <span class="ml-1 font-bold tabular-nums" x-text="'Difference: ₱' + Math.abs(totalDebit - totalCredit).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></span>
                </p>
            </template>
            <template x-if="!!attachmentError">
                <p class="text-right text-[11.5px] font-medium text-red-600">
                    <i data-lucide="info" class="inline h-3.5 w-3.5 align-middle"></i>
                    Fix the attachment size error before saving.
                </p>
            </template>

            @if (auth()->user()->isAccounting() && $voucher->approval_status === 'approved')
            <div class="rounded-lg border border-violet-200 bg-violet-50/40 p-4">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-violet-700">
                    Reason for this change <span class="font-semibold text-red-400">*</span>
                </label>
                <p class="mt-0.5 text-[11px] text-slate-500">This voucher is already approved — your change goes to the CFO as an edit request, not applied directly.</p>
                <textarea name="reason" rows="2" required
                          class="mt-2 block w-full rounded-lg border-violet-200 text-[13px] focus:border-violet-400 focus:ring-violet-400"
                          placeholder="e.g. Corrected payee name and added the installation fee missed in the original voucher.">{{ old('reason') }}</textarea>
            </div>
            @endif

            <div class="flex items-center justify-between gap-3">
                <p class="text-[11px] text-slate-400">
                    Fields marked <span class="font-semibold text-red-400">*</span> are required.
                </p>
                <div class="flex items-center gap-3">
                    <a href="{{ route('vouchers.show', $voucher) }}"
                       class="rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-[13px] font-semibold text-gray-600 transition hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            :disabled="!!attachmentError || !isBalanced"
                            class="inline-flex items-center gap-2 rounded-lg bg-omet-blue px-6 py-2.5 text-[13px] font-semibold text-white shadow-sm transition hover:bg-omet-lightblue disabled:cursor-not-allowed disabled:opacity-50">
                        <i data-lucide="check" class="h-4 w-4"></i> {{ auth()->user()->isAccounting() && $voucher->approval_status === 'approved' ? 'Submit Edit Request' : 'Update Voucher' }}
                    </button>
                </div>
            </div>
        </div>

    </form>

</div>
</x-app-layout>
