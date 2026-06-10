{{-- VOUCHER DETAILS modal — payment history + attachments. Expects Alpine scope `vouchersPage` (detail, showDetails) --}}
<div x-cloak x-show="showDetails"
     class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-6"
     @keydown.escape.window="closeDetails()">
    <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl" @click.outside="closeDetails()">
        <div class="flex max-h-[90vh] flex-col">

            <div class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <p class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Voucher details</p>
                    <h2 class="mt-0.5 text-lg font-semibold text-omet-navy"><span x-text="detail.voucher_no"></span> · <span x-text="detail.payee_name"></span></h2>
                    <p class="mt-1 text-[12px] text-gray-500">
                        <span x-text="detail.status_label"></span> ·
                        Payable ₱<span x-text="Number(detail.amount_payable).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></span> ·
                        Paid ₱<span x-text="Number(detail.paid).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></span> ·
                        Balance ₱<span class="font-semibold" x-text="Number(detail.balance).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></span>
                    </p>
                </div>
                <button type="button" @click="closeDetails()" class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-6">

                {{-- Payment history --}}
                <section>
                    <h3 class="mb-2 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        <i data-lucide="banknote" class="h-3.5 w-3.5"></i> Payment history
                        <span class="font-normal normal-case text-slate-400" x-text="'(' + detail.payments.length + ')'"></span>
                    </h3>

                    <template x-if="detail.payments.length === 0">
                        <p class="rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-[12px] text-slate-400">No payments recorded yet.</p>
                    </template>

                    <template x-if="detail.payments.length > 0">
                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-100 text-[12px]">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-500">Date</th>
                                        <th class="px-3 py-2 text-right font-semibold text-slate-500">Amount</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-500">Mode / check</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-500">Account</th>
                                        <th class="px-3 py-2 text-right font-semibold text-slate-500">Reverse</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="p in detail.payments" :key="p.id">
                                        <tr class="align-top">
                                            <td class="whitespace-nowrap px-3 py-2 text-slate-600" x-text="p.paid_on"></td>
                                            <td class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums text-omet-navy">
                                                ₱<span x-text="Number(p.amount).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></span>
                                            </td>
                                            <td class="px-3 py-2 text-slate-600">
                                                <span x-text="p.mode_label"></span>
                                                <template x-if="p.check_no"><span class="block text-[10.5px] text-slate-400">Check <span x-text="p.check_no"></span><span x-show="p.check_date"> · <span x-text="p.check_date"></span></span></span></template>
                                                <template x-if="p.is_pdc"><span class="mt-0.5 inline-block rounded-full bg-violet-50 px-1.5 py-0.5 text-[10px] font-semibold text-violet-700 ring-1 ring-violet-100 ring-inset">PDC</span></template>
                                            </td>
                                            <td class="px-3 py-2 text-slate-500" x-text="p.bank_account || '—'"></td>
                                            <td class="px-3 py-2 text-right">
                                                <form method="POST" :action="'{{ url('/vouchers/payments') }}/' + p.id"
                                                      @submit="if (! confirm('Reverse this payment of ₱' + Number(p.amount).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) + '? The bank ledger and project rows it created will be removed.')) $event.preventDefault();">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-red-200 bg-red-50 px-2 py-1 text-[10.5px] font-semibold text-red-600 shadow-sm transition hover:bg-red-100">
                                                        <i data-lucide="undo-2" class="h-3 w-3 pointer-events-none"></i> Reverse
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </section>

                {{-- Attachments --}}
                <section>
                    <h3 class="mb-2 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        <i data-lucide="paperclip" class="h-3.5 w-3.5"></i> Attachments
                        <span class="font-normal normal-case text-slate-400" x-text="'(' + detail.attachments.length + ')'"></span>
                    </h3>

                    <template x-if="detail.attachments.length > 0">
                        <ul class="mb-3 divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
                            <template x-for="a in detail.attachments" :key="a.id">
                                <li class="flex items-center justify-between gap-3 px-3 py-2 text-[12px]">
                                    <a :href="a.download_url" class="flex min-w-0 items-center gap-2 text-slate-700 hover:text-omet-blue hover:underline">
                                        <i data-lucide="file-text" class="h-3.5 w-3.5 shrink-0 text-slate-400"></i>
                                        <span class="truncate" x-text="a.name"></span>
                                    </a>
                                    <div class="flex shrink-0 items-center gap-2 text-[10.5px] text-slate-400">
                                        <span x-text="a.size"></span>
                                        <span>·</span>
                                        <span x-text="a.uploaded_at"></span>
                                        <form method="POST" :action="'{{ url('/vouchers/attachments') }}/' + a.id"
                                              @submit="if (! confirm('Remove \'' + a.name + '\'?')) $event.preventDefault();">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-md p-1 text-slate-400 transition hover:bg-red-50 hover:text-red-600">
                                                <i data-lucide="trash-2" class="h-3 w-3 pointer-events-none"></i>
                                            </button>
                                        </form>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </template>

                    <template x-if="detail.attachments.length === 0">
                        <p class="mb-3 rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-[12px] text-slate-400">No supporting documents attached yet.</p>
                    </template>

                    @error('file')
                        <p class="mb-2 text-[11px] font-medium text-red-600">{{ $message }}</p>
                    @enderror

                    <form method="POST" enctype="multipart/form-data" class="flex items-center gap-2"
                          :action="'{{ url('/vouchers') }}/' + detail.id + '/attachments'">
                        @csrf
                        <input type="hidden" name="attachment_voucher_id" :value="detail.id">
                        <input type="file" name="file" required
                               class="block w-full flex-1 text-[12px] text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-omet-blue/10 file:px-3 file:py-1.5 file:text-[11.5px] file:font-semibold file:text-omet-blue hover:file:bg-omet-blue/20">
                        <button type="submit" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-omet-blue px-3 py-1.5 text-[11.5px] font-semibold text-white shadow-sm transition hover:bg-omet-lightblue">
                            <i data-lucide="upload" class="h-3.5 w-3.5"></i> Upload
                        </button>
                    </form>
                    <p class="mt-1 text-[10.5px] text-slate-400">PDF, image, Word or Excel files up to 10 MB — invoices, ORs, signed checks, approval slips.</p>
                </section>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50/50 px-6 py-3">
                <button type="button" @click="closeDetails()" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-[12.5px] font-semibold text-gray-600 transition hover:bg-gray-50">Close</button>
            </div>
        </div>
    </div>
</div>
