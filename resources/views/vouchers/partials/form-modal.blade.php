{{-- NEW / EDIT VOUCHER modal — expects Alpine scope `vouchersPage` (f, editId, showForm) --}}
<div x-cloak x-show="showForm"
     class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-6"
     @keydown.escape.window="closeForm()">
    <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl" @click.outside="closeForm()">
        <form method="POST" enctype="multipart/form-data" class="flex max-h-[90vh] flex-col"
              x-bind:action="editId ? '{{ url('/vouchers') }}/' + editId : '{{ route('vouchers.store') }}'"
              @submit.prevent="if (attachmentError) { return; } $el.submit()">
            @csrf
            <template x-if="editId">
                <div>
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="editing_voucher_id" :value="editId">
                </div>
            </template>

            <div class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <p class="text-[10.5px] font-semibold uppercase tracking-wider text-omet-blue">Disbursement</p>
                    <h2 class="mt-0.5 text-lg font-semibold text-omet-navy" x-text="editId ? 'Edit voucher' : 'New voucher'"></h2>
                    <p class="mt-1 text-[12px] text-gray-500">Records a payable. Money only leaves when you record a payment.</p>
                </div>
                <button type="button" @click="closeForm()" class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Voucher No. *</label>
                        <input type="text" name="voucher_no" required x-model="f.voucher_no" placeholder="e.g. 2026-0001"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Voucher date *</label>
                        <input type="date" name="voucher_date" required x-model="f.voucher_date"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Due date <span class="text-gray-400">(payable)</span></label>
                        <input type="date" name="due_date" x-model="f.due_date"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Release date <span class="text-gray-400">(actual)</span></label>
                        <input type="date" name="release_date" x-model="f.release_date"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                </div>

                <template x-if="lockedFields">
                    <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11.5px] text-amber-800">
                        <i data-lucide="lock" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i>
                        <span>Payee, amount payable and project are locked because payments have already been recorded against this voucher — changing them now would desync the postings they created. Reverse the payments first if these need to change.</span>
                    </div>
                </template>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {{-- Payee combobox --}}
                    <div class="relative" @click.outside="payeeOpen = false">
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Payee *</label>

                        <template x-if="!payeeOther">
                            <div>
                                <button type="button" :disabled="lockedFields"
                                        @click="if (!lockedFields) { payeeOpen = !payeeOpen; if (payeeOpen) $nextTick(() => $refs.payeeSearch?.focus()) }"
                                        class="flex h-10 w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 text-left text-[13px] text-gray-800 outline-none transition hover:border-slate-300 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500">
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
                                        <p x-show="filteredOptions(payees, payeeQuery).length === 0" class="px-3 py-3 text-center text-[11px] text-gray-400">No payees match.</p>
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
                                <input x-ref="payeeOtherInput" type="text" name="payee_name" required x-model="f.payee_name" :readonly="lockedFields"
                                       placeholder="Type payee name"
                                       class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none read-only:bg-slate-50 read-only:text-slate-500 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                                <button type="button" x-show="!lockedFields" @click="payeeOther = false; f.payee_name = ''"
                                        class="h-10 shrink-0 rounded-lg border border-slate-200 bg-white px-3 text-[11px] text-gray-500 transition hover:bg-slate-50">List</button>
                            </div>
                        </template>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Amount payable (PHP) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount_payable" required x-model="f.amount_payable" :readonly="lockedFields" placeholder="0.00"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] tabular-nums text-gray-800 outline-none read-only:bg-slate-50 read-only:text-slate-500 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {{-- Project combobox --}}
                    <div class="relative" @click.outside="projOpen = false">
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Project</label>
                        <div class="flex items-stretch gap-1">
                            <button type="button" :disabled="lockedFields"
                                    @click="if (!lockedFields) { projOpen = !projOpen; if (projOpen) $nextTick(() => $refs.projSearch?.focus()) }"
                                    class="flex h-10 flex-1 items-center justify-between rounded-lg border border-slate-200 bg-white px-3 text-left text-[13px] text-gray-800 outline-none transition hover:border-slate-300 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500">
                                <span class="truncate" x-text="projectLabel(f.project_id)"></span>
                                <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-gray-400"></i>
                            </button>
                            <button type="button" x-show="f.project_id && !lockedFields" @click.stop="f.project_id = ''"
                                    class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-[11px] text-gray-500 transition hover:bg-rose-50 hover:text-rose-600">Clear</button>
                        </div>
                        <input type="hidden" name="project_id" :value="f.project_id">
                        <div x-show="projOpen" x-cloak
                             class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                            <div class="border-b border-slate-100 p-2">
                                <input x-ref="projSearch" x-model="projQuery" type="text" placeholder="Search project…"
                                       class="h-8 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[12px] outline-none focus:border-omet-blue focus:bg-white">
                            </div>
                            <div class="max-h-56 overflow-y-auto py-1">
                                <button type="button" @click="f.project_id = ''; projOpen = false; projQuery = ''"
                                        class="flex w-full px-3 py-2 text-left text-[12px] text-slate-500 hover:bg-slate-50">— none —</button>
                                <template x-for="p in filteredOptions(projects, projQuery)" :key="p.id">
                                    <button type="button" @click="f.project_id = String(p.id); projOpen = false; projQuery = ''"
                                            class="flex w-full items-start justify-between gap-2 px-3 py-2 text-left text-[12px] hover:bg-blue-50"
                                            :class="String(p.id) === String(f.project_id) ? 'bg-blue-50/60 font-medium text-omet-blue' : 'text-gray-700'">
                                        <span>
                                            <span class="block font-medium" x-text="p.label"></span>
                                            <span class="block text-[10px] text-gray-400" x-text="p.kind"></span>
                                        </span>
                                    </button>
                                </template>
                                <p x-show="filteredOptions(projects, projQuery).length === 0" class="px-3 py-3 text-center text-[11px] text-gray-400">No projects match.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Source bank account combobox --}}
                    <div class="relative" @click.outside="acctOpen = false">
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Source bank account <span class="text-gray-400">(money out)</span></label>
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
                                <p x-show="filteredOptions(accounts, acctQuery).length === 0" class="px-3 py-3 text-center text-[11px] text-gray-400">No accounts match.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Category combobox --}}
                <div class="relative" @click.outside="categoryOpen = false">
                    <label class="mb-1 block text-[11px] font-medium text-gray-600">Category *</label>
                    <button type="button"
                            @click="categoryOpen = !categoryOpen; if (categoryOpen) $nextTick(() => $refs.categorySearch?.focus())"
                            class="flex h-10 w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 text-left text-[13px] text-gray-800 outline-none transition hover:border-slate-300 focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                        <span class="min-w-0 flex-1 truncate" x-text="categoryLabel(f.category_id)"></span>
                        <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-gray-400"></i>
                    </button>
                    <input type="hidden" name="category_id" :value="f.category_id" required>
                    <div x-show="categoryOpen" x-cloak
                         class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                        <div class="border-b border-slate-100 p-2">
                            <input x-ref="categorySearch" x-model="categoryQuery" type="text" placeholder="Search category…"
                                   class="h-8 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-[12px] outline-none focus:border-omet-blue focus:bg-white">
                        </div>
                        <div class="max-h-56 overflow-y-auto py-1">
                            <template x-for="c in filteredOptions(categories, categoryQuery)" :key="c.id">
                                <button type="button" @click="f.category_id = String(c.id); categoryOpen = false; categoryQuery = ''"
                                        class="flex w-full px-3 py-2 text-left text-[12px] hover:bg-blue-50"
                                        :class="String(c.id) === String(f.category_id) ? 'bg-blue-50/60 font-medium text-omet-blue' : 'text-gray-700'">
                                    <span class="block truncate" x-text="c.label"></span>
                                </button>
                            </template>
                            <p x-show="filteredOptions(categories, categoryQuery).length === 0" class="px-3 py-3 text-center text-[11px] text-gray-400">No categories match.</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {{-- Type combobox --}}
                    <div class="relative" @click.outside="typeOpen = false">
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Type</label>
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
                                            :class="f.transaction_type === t.id ? 'bg-blue-50/60 font-medium text-omet-blue' : 'text-gray-700'">
                                        <span x-text="t.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Mode of payment combobox --}}
                    <div class="relative" @click.outside="modeOpen = false">
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Mode of payment</label>
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
                                            :class="f.mode_of_payment === m.id ? 'bg-blue-50/60 font-medium text-omet-blue' : 'text-gray-700'">
                                        <span x-text="m.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">PO Number</label>
                        <input type="text" name="po_number" x-model="f.po_number" placeholder="e.g. PO6132"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>

                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Reference (PR / OR / SI)</label>
                        <input type="text" name="reference" x-model="f.reference"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>

                    {{-- Payment status — selecting "Paid" records a payment for
                         the remaining balance. Once fully paid this is locked;
                         reverse payment(s) from the voucher view to undo. --}}
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Payment status</label>

                        <template x-if="isCancelled">
                            <div>
                                <input type="text" value="Cancelled" disabled
                                       class="h-10 w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 text-[13px] text-slate-500 outline-none">
                                <input type="hidden" name="payment_status" value="unpaid">
                                <p class="mt-1 text-[10.5px] text-gray-400">Reactivate this voucher before changing payment status.</p>
                            </div>
                        </template>

                        <template x-if="!isCancelled && !alreadyPaid">
                            <div>
                                <select name="payment_status" x-model="f.payment_status"
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                                    <option value="unpaid">Unpaid</option>
                                    <option value="paid">Paid</option>
                                </select>
                                <template x-if="hasPartialPayment">
                                    <p class="mt-1 text-[10.5px] text-gray-400">
                                        Currently <span class="font-medium text-slate-500" x-text="statusLabel(f.voucher_status)"></span>.
                                        Selecting Paid records the remaining <span class="font-medium" x-text="formatPeso(f.balance_due)"></span>.
                                    </p>
                                </template>
                                <template x-if="f.payment_status === 'paid'">
                                    <p class="mt-1 text-[10.5px] text-gray-400">
                                        <span x-text="editId ? 'A payment for the remaining balance will be recorded immediately, dated today.' : 'A full payment will be recorded immediately, dated to the voucher date.'"></span>
                                        <template x-if="editId && f.balance_due > 0">
                                            <span> Amount: <span class="font-medium" x-text="formatPeso(f.balance_due)"></span>.</span>
                                        </template>
                                    </p>
                                </template>
                                <template x-if="f.payment_status === 'paid' && !f.source_bank_account_id">
                                    <p class="mt-1 text-[10.5px] font-medium text-amber-700">Set a source bank account — required to post the payment to the ledger.</p>
                                </template>
                            </div>
                        </template>

                        <template x-if="!isCancelled && alreadyPaid">
                            <div>
                                <input type="text" value="Paid" disabled
                                       class="h-10 w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 text-[13px] text-slate-500 outline-none">
                                <input type="hidden" name="payment_status" value="paid">
                                <p class="mt-1 text-[10.5px] text-gray-400">Already fully paid. To undo, reverse the payment(s) from the voucher's view page.</p>
                            </div>
                        </template>
                    </div>
                </div>

                <template x-if="editId && !isCancelled">
                    <p class="text-[11px] text-gray-400">
                        <i data-lucide="info" class="inline h-3 w-3 -mt-0.5"></i>
                        Partial and PDC statuses are set automatically from recorded payments. Use <span class="font-medium text-slate-500">Cancel</span> on the register to void a voucher.
                    </p>
                </template>

                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-600">Particular / description</label>
                    <textarea name="particular" rows="2" x-model="f.particular"
                              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-[12.5px] text-gray-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10"></textarea>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Remarks <span class="text-gray-400">(col O)</span></label>
                        <textarea name="remarks" rows="2" x-model="f.remarks" placeholder="e.g. UNLIQUIDATED, Check deposit to Sterling…"
                                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-[12.5px] text-gray-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Source of fund <span class="text-gray-400">(col T)</span></label>
                        <textarea name="source_of_fund" rows="2" x-model="f.source_of_fund" placeholder="e.g. from Jan 12 1.65M encashment…"
                                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-[12.5px] text-gray-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10"></textarea>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">OR / CR / SI / CI reference <span class="text-gray-400">(col U)</span></label>
                        <input type="text" name="or_ref" x-model="f.or_ref" placeholder="e.g. OR-12345 / Ref. No.: BBS400…"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Change / excess returned <span class="text-gray-400">(col V)</span></label>
                        <input type="number" step="0.01" min="0" name="change_amount" x-model="f.change_amount" placeholder="0.00"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] tabular-nums text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-600">Notes <span class="text-gray-400">(internal)</span></label>
                    <textarea name="notes" rows="2" x-model="f.notes"
                              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-[12.5px] text-gray-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10"></textarea>
                </div>

                {{-- Optional attachments --}}
                <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50/50 px-4 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-[11px] font-semibold text-gray-700">Attachments <span class="font-normal text-gray-400">(optional)</span></p>
                            <p class="mt-0.5 text-[10.5px] text-gray-400">PDF, images, Word or Excel · max 10 MB each</p>
                        </div>
                        <i data-lucide="paperclip" class="h-4 w-4 text-slate-300"></i>
                    </div>

                    <template x-if="editId && f.attachments.length > 0">
                        <ul class="mt-3 space-y-1.5">
                            <template x-for="a in f.attachments" :key="a.id">
                                <li class="flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[11.5px] text-slate-700">
                                    <i data-lucide="file-text" class="h-3.5 w-3.5 shrink-0 text-slate-400"></i>
                                    <span class="min-w-0 truncate" x-text="a.name"></span>
                                    <span class="shrink-0 text-slate-400" x-text="a.size"></span>
                                </li>
                            </template>
                        </ul>
                        <p class="mt-2 text-[10.5px] text-slate-400">To remove an existing file, open the voucher's <span class="font-medium text-slate-500">view page</span>.</p>
                    </template>

                    <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                           @change="validateAttachments($event.target)"
                           class="mt-3 block w-full cursor-pointer text-[12px] text-slate-600 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-omet-blue file:px-3 file:py-1.5 file:text-[11px] file:font-semibold file:text-white hover:file:bg-omet-lightblue">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50/50 px-6 py-3">
                <button type="button" @click="closeForm()" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-[12.5px] font-semibold text-gray-600 transition hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="!!attachmentError"
                        class="inline-flex items-center gap-2 rounded-lg bg-omet-blue px-5 py-2 text-[12.5px] font-semibold text-white shadow-sm transition hover:bg-omet-lightblue disabled:cursor-not-allowed disabled:opacity-50">
                    <i data-lucide="check" class="h-3.5 w-3.5"></i>
                    <span x-text="editId ? 'Save changes' : 'Create voucher'"></span>
                </button>
            </div>
        </form>
    </div>

    {{-- Attachment size error modal --}}
    <div x-show="attachmentError" x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 px-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100">
                    <i data-lucide="alert-circle" class="h-5 w-5 text-red-600"></i>
                </div>
                <div>
                    <h3 class="text-[14px] font-semibold text-slate-800">File too large</h3>
                    <p class="mt-1 text-[12.5px] text-slate-600">
                        <span class="font-medium" x-text="attachmentError"></span>
                        exceeds the 10 MB limit. Please compress or split the file before uploading.
                    </p>
                </div>
            </div>
            <div class="mt-5 flex justify-end">
                <button @click="attachmentError = ''" type="button"
                        class="rounded-lg bg-omet-blue px-5 py-2 text-[12.5px] font-semibold text-white transition hover:bg-omet-lightblue">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>
