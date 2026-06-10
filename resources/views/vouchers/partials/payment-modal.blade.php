{{-- RECORD PAYMENT modal — expects Alpine scope `vouchersPage` (p, payVoucher, showPay) --}}
<div x-cloak x-show="showPay"
     class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-6"
     @keydown.escape.window="closePay()">
    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl" @click.outside="closePay()">
        <form method="POST" class="flex max-h-[90vh] flex-col"
              x-bind:action="'{{ url('/vouchers') }}/' + payVoucher.id + '/payments'">
            @csrf
            <input type="hidden" name="paying_voucher_id" :value="payVoucher.id">

            <div class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <p class="text-[10.5px] font-semibold uppercase tracking-wider text-emerald-600">Record payment</p>
                    <h2 class="mt-0.5 text-lg font-semibold text-omet-navy"><span x-text="payVoucher.no"></span> · <span x-text="payVoucher.payee"></span></h2>
                    <p class="mt-1 text-[12px] text-gray-500">Balance due: ₱<span class="font-semibold tabular-nums" x-text="Number(payVoucher.balance).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></span></p>
                </div>
                <button type="button" @click="closePay()" class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-600">Pay from bank account *</label>
                    <select name="bank_account_id" x-model="p.bank_account_id"
                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                        <option value="">— pick account (deducts its balance) —</option>
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}">{{ $a->entity?->name ? $a->entity->name . ' — ' : '' }}{{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Paid on *</label>
                        <input type="date" name="paid_on" required x-model="p.paid_on"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Amount (PHP) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required x-model="p.amount" placeholder="0.00"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] tabular-nums text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Mode</label>
                        <select name="mode" x-model="p.mode"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                            @foreach ($modes as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Check no.</label>
                        <input type="text" name="check_no" x-model="p.check_no"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-gray-600">Check date <span class="text-gray-400">(future = PDC)</span></label>
                        <input type="date" name="check_date" x-model="p.check_date"
                               class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-600">Notes</label>
                    <input type="text" name="notes" x-model="p.notes"
                           class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-gray-800 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
                </div>

                <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 p-3 text-[12px] text-emerald-900">
                    <p class="font-semibold">What this does</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5 text-emerald-800">
                        <li>Posts an outflow on the selected bank account (deducts its balance).</li>
                        <li x-show="payVoucher.id">Records a project expense if the voucher is tagged to a project.</li>
                        <li>Updates the voucher status (Paid / Partially Paid / PDC).</li>
                    </ul>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50/50 px-6 py-3">
                <button type="button" @click="closePay()" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-[12.5px] font-semibold text-gray-600 transition hover:bg-gray-50">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2 text-[12.5px] font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                    <i data-lucide="banknote" class="h-3.5 w-3.5"></i> Record payment
                </button>
            </div>
        </form>
    </div>
</div>
