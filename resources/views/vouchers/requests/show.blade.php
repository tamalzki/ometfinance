<x-app-layout page-title="Review Request">
@php
    $peso = fn ($n) => '₱' . number_format((float) $n, 2);
    $voucher = $voucherRequest->voucher;

    $typeTone = [
        'create'  => 'bg-amber-50 text-amber-700 ring-amber-100',
        'edit'    => 'bg-violet-50 text-violet-700 ring-violet-100',
        'delete'  => 'bg-rose-50 text-rose-600 ring-rose-100',
        'payment' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
    ];

    $amountChange = collect($changedFields)->firstWhere('key', 'amount_payable');
@endphp

<div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4" x-data="{ showRejectNote: false }">

@if (session('success'))
    <div class="flex shrink-0 items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs font-medium text-green-800">
        <i data-lucide="check-circle-2" class="h-3.5 w-3.5 shrink-0 text-green-600"></i>
        {{ session('success') }}
    </div>
@endif

<a href="{{ route('voucher-requests.index') }}"
   class="-mb-1 inline-flex w-fit items-center gap-1 text-[11px] font-medium text-slate-500 hover:text-omet-navy">
    <i data-lucide="arrow-left" class="h-3 w-3"></i> Back to Voucher Approvals
</a>

{{-- ── Header card ──────────────────────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-omet-blue">Disbursement</p>
            <h1 class="text-lg font-bold text-omet-navy">{{ $voucher->voucher_no }} · {{ $voucher->payee_name }}</h1>
            <div class="mt-1.5 flex items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $typeTone[$voucherRequest->type] ?? '' }}">
                    <i data-lucide="git-pull-request" class="h-3 w-3"></i> {{ $voucherRequest->typeLabel() }}
                </span>
                <span class="text-[11px] text-slate-400">{{ $voucher->isPendingApproval() ? 'Submitted' : 'Originally approved' }} {{ $voucher->created_at->format('M j, Y') }}</span>
            </div>
        </div>
        @if ($voucherRequest->isEdit() && $amountChange)
        <div class="w-full shrink-0 text-left sm:w-auto sm:text-right">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Original Amount</p>
            <p class="text-sm font-semibold text-slate-400 line-through">{{ $amountChange['before'] }}</p>
            <p class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Revised Amount</p>
            <p class="text-lg font-bold text-omet-navy">{{ $amountChange['after'] }}</p>
        </div>
        @else
        <div class="w-full shrink-0 text-left sm:w-auto sm:text-right">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Amount Payable</p>
            <p class="text-lg font-bold text-omet-navy">{{ $peso($voucher->amount_payable) }}</p>
        </div>
        @endif
    </div>
</div>

{{-- ── Request panel ────────────────────────────────────────────────────── --}}
<div class="min-h-0 flex-1 overflow-y-auto rounded-xl border border-indigo-100 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4 {{ $voucherRequest->isPending() ? 'bg-indigo-50/40' : ($voucherRequest->isRejected() ? 'bg-rose-50/40' : 'bg-emerald-50/40') }}">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $voucherRequest->isPending() ? 'bg-indigo-100' : ($voucherRequest->isRejected() ? 'bg-rose-100' : 'bg-emerald-100') }}">
            <i data-lucide="{{ $voucherRequest->isPending() ? 'shield-question' : ($voucherRequest->isRejected() ? 'x-circle' : 'check-circle-2') }}"
               class="h-4 w-4 {{ $voucherRequest->isPending() ? 'text-indigo-600' : ($voucherRequest->isRejected() ? 'text-rose-600' : 'text-emerald-600') }}"></i>
        </span>
        <div>
            <h3 class="text-sm font-semibold text-omet-navy">
                {{ $voucherRequest->typeLabel() }} —
                {{ $voucherRequest->isPending() ? 'CFO Approval Needed' : ($voucherRequest->isRejected() ? 'Rejected' : 'Approved') }}
            </h3>
            <p class="text-[11px] text-slate-500">
                Requested by <span class="font-medium text-slate-700">{{ $voucherRequest->requestedBy->name ?? '—' }}</span>
                · {{ $voucherRequest->created_at->format('M j, Y \a\t g:i A') }}
                @if ($voucherRequest->isEdit())
                · {{ count($changedFields) }} field{{ count($changedFields) === 1 ? '' : 's' }} changed
                @if ($entriesDiff && collect($entriesDiff['rows'])->contains(fn ($r) => $r['state'] !== 'unchanged'))
                · entries modified
                @endif
                @endif
            </p>
        </div>
    </div>

    <div class="p-5">

        @if ($voucherRequest->isEdit())
            {{-- Changed fields table --}}
            @if (count($changedFields) > 0)
            <p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Changed fields only</p>
            <div class="overflow-hidden rounded-lg border border-slate-200">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50">
                            <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-400">Field</th>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-rose-500">Before (Approved)</th>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-emerald-600">After (Requested)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($changedFields as $f)
                        <tr>
                            <td class="px-3 py-2 text-[12.5px] font-medium text-slate-600">{{ $f['label'] }}</td>
                            <td class="bg-rose-50/40 px-3 py-2 text-[12.5px] text-rose-500 line-through">{{ $f['before'] }}</td>
                            <td class="bg-emerald-50/40 px-3 py-2 text-[12.5px] font-semibold text-emerald-700">{{ $f['after'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Accounting entries diff --}}
            @if ($entriesDiff && count($entriesDiff['rows']) > 0)
            <p class="mb-2 mt-5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Accounting entries — changes</p>
            <div class="space-y-1.5">
                @foreach ($entriesDiff['rows'] as $row)
                @php
                    $entryTypeOf = fn ($e) => is_array($e) ? ($e['entry_type'] ?? null) : $e?->entry_type;
                    $amountOf    = fn ($e) => is_array($e) ? (float) ($e['amount'] ?? 0) : (float) $e?->amount;
                    $labelOf     = fn ($e) => is_array($e) ? ($e['category_label'] ?? '—') : ($e?->category?->fullLabel() ?? '—');
                @endphp
                @if ($row['state'] === 'unchanged')
                    <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 text-[12px] text-slate-400">
                        <span class="inline-flex items-center gap-1.5"><span class="rounded bg-slate-200 px-1.5 py-0.5 text-[9px] font-bold uppercase text-slate-500">No change</span>
                            <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[9px] font-bold uppercase text-indigo-600">{{ strtoupper(substr($entryTypeOf($row['before']), 0, 2)) }}</span>
                            {{ $labelOf($row['before']) }}</span>
                        <span class="tabular-nums">{{ $peso($amountOf($row['before'])) }}</span>
                    </div>
                @elseif ($row['state'] === 'modified')
                    <div class="rounded-lg border border-amber-200 bg-amber-50/50 px-3 py-2 text-[12px]">
                        <p class="mb-1.5 text-[9px] font-bold uppercase tracking-wide text-amber-700">Modified entry</p>
                        <div class="flex items-center justify-between text-rose-500">
                            <span class="inline-flex items-center gap-1.5 line-through"><span class="rounded bg-rose-100 px-1.5 py-0.5 text-[9px] font-bold uppercase">{{ strtoupper(substr($entryTypeOf($row['before']), 0, 2)) }}</span> {{ $labelOf($row['before']) }}</span>
                            <span class="tabular-nums line-through">{{ $peso($amountOf($row['before'])) }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between font-semibold text-emerald-700">
                            <span class="inline-flex items-center gap-1.5"><span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[9px] font-bold uppercase">{{ strtoupper(substr($entryTypeOf($row['after']), 0, 2)) }}</span> {{ $labelOf($row['after']) }}</span>
                            <span class="tabular-nums">{{ $peso($amountOf($row['after'])) }} <span class="text-[10px] text-emerald-500">+{{ $peso($amountOf($row['after']) - $amountOf($row['before'])) }}</span></span>
                        </div>
                    </div>
                @elseif ($row['state'] === 'added')
                    <div class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50/50 px-3 py-2 text-[12px] font-semibold text-emerald-700">
                        <span class="inline-flex items-center gap-1.5"><span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[9px] font-bold uppercase">New</span>
                            <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[9px] font-bold uppercase text-indigo-600">{{ strtoupper(substr($entryTypeOf($row['after']), 0, 2)) }}</span>
                            {{ $labelOf($row['after']) }}
                            @if (! empty($row['after']['project_name']))
                            <span class="rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium normal-case text-emerald-700">Project: {{ $row['after']['project_name'] }}</span>
                            @endif
                        </span>
                        <span class="tabular-nums">{{ $peso($amountOf($row['after'])) }}</span>
                    </div>
                @else {{-- removed --}}
                    <div class="flex items-center justify-between rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2 text-[12px] font-semibold text-rose-500 line-through">
                        <span class="inline-flex items-center gap-1.5"><span class="rounded bg-rose-100 px-1.5 py-0.5 text-[9px] font-bold uppercase">Removed</span> {{ $labelOf($row['before']) }}</span>
                        <span class="tabular-nums">{{ $peso($amountOf($row['before'])) }}</span>
                    </div>
                @endif
                @endforeach
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-4 rounded-lg bg-slate-50 px-3 py-2 text-[11px]">
                <span class="font-semibold text-slate-500">DEBIT <span class="text-slate-400 line-through">{{ $peso($entriesDiff['totalDebitBefore']) }}</span> → <span class="text-omet-navy">{{ $peso($entriesDiff['totalDebitAfter']) }}</span></span>
                <span class="font-semibold text-slate-500">CREDIT <span class="text-slate-400 line-through">{{ $peso($entriesDiff['totalCreditBefore']) }}</span> → <span class="text-omet-navy">{{ $peso($entriesDiff['totalCreditAfter']) }}</span></span>
                @if (abs($entriesDiff['totalDebitAfter'] - $entriesDiff['totalCreditAfter']) < 0.01)
                <span class="ml-auto inline-flex items-center gap-1 font-semibold text-emerald-600"><i data-lucide="check-circle-2" class="h-3.5 w-3.5"></i> Still Balanced</span>
                @else
                <span class="ml-auto inline-flex items-center gap-1 font-semibold text-rose-600"><i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i> Out of balance</span>
                @endif
            </div>
            @endif
        @elseif ($voucherRequest->isCreate())
            <p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Full voucher details</p>
            @include('vouchers.partials.check-voucher-document', ['voucher' => $voucher])
        @elseif ($voucherRequest->isPayment())
            @php
                $payload = $voucherRequest->payload ?? [];
                $payAccount = ! empty($payload['bank_account_id']) ? $accounts->firstWhere('id', (int) $payload['bank_account_id']) : null;
            @endphp
            <p class="mb-2 rounded-lg border border-emerald-100 bg-emerald-50/50 px-3 py-2.5 text-[12.5px] text-emerald-700">
                Approving posts this payment — deducts the bank account, updates the voucher status, and syncs project outflow. Rejecting leaves the voucher unpaid.
            </p>
            <p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Proposed payment</p>
            <div class="overflow-hidden rounded-lg border border-slate-200">
                <table class="w-full">
                    <tbody class="divide-y divide-slate-100 text-[12.5px]">
                        <tr>
                            <td class="w-1/3 bg-slate-50 px-3 py-2 font-medium text-slate-500">Amount</td>
                            <td class="px-3 py-2 font-bold text-omet-navy">{{ $peso($payload['amount'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td class="bg-slate-50 px-3 py-2 font-medium text-slate-500">Balance due (current)</td>
                            <td class="px-3 py-2 text-slate-700">{{ $peso($voucher->balanceDue()) }}</td>
                        </tr>
                        <tr>
                            <td class="bg-slate-50 px-3 py-2 font-medium text-slate-500">Paid on</td>
                            <td class="px-3 py-2 text-slate-700">{{ \Illuminate\Support\Carbon::parse($payload['paid_on'] ?? null)->format('M j, Y') }}</td>
                        </tr>
                        <tr>
                            <td class="bg-slate-50 px-3 py-2 font-medium text-slate-500">Bank account</td>
                            <td class="px-3 py-2 text-slate-700">{{ $payAccount ? (($payAccount->entity?->name ? $payAccount->entity->name . ' — ' : '') . $payAccount->name) : '— none —' }}</td>
                        </tr>
                        <tr>
                            <td class="bg-slate-50 px-3 py-2 font-medium text-slate-500">Mode of payment</td>
                            <td class="px-3 py-2 text-slate-700">{{ \App\Models\Voucher::MODES[$payload['mode'] ?? ''] ?? '—' }}</td>
                        </tr>
                        @if (! empty($payload['check_no']))
                        <tr>
                            <td class="bg-slate-50 px-3 py-2 font-medium text-slate-500">Check no.</td>
                            <td class="px-3 py-2 text-slate-700">{{ $payload['check_no'] }}</td>
                        </tr>
                        @endif
                        @if (! empty($payload['check_date']))
                        <tr>
                            <td class="bg-slate-50 px-3 py-2 font-medium text-slate-500">Check date</td>
                            <td class="px-3 py-2 text-slate-700">{{ \Illuminate\Support\Carbon::parse($payload['check_date'])->format('M j, Y') }}</td>
                        </tr>
                        @endif
                        @if (! empty($payload['notes']))
                        <tr>
                            <td class="bg-slate-50 px-3 py-2 font-medium text-slate-500">Notes</td>
                            <td class="px-3 py-2 text-slate-700">{{ $payload['notes'] }}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @else {{-- delete --}}
            <p class="mb-2 rounded-lg border border-rose-100 bg-rose-50/50 px-3 py-2.5 text-[12.5px] text-rose-700">
                Approving will delete this voucher and reverse any ledger or project rows it posted. Rejecting leaves it active and unchanged.
            </p>
            <p class="mb-2 mt-4 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Full voucher details</p>
            @include('vouchers.partials.check-voucher-document', ['voucher' => $voucher])
        @endif

        <p class="mb-2 mt-5 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            <i data-lucide="paperclip" class="h-3 w-3"></i> Attachments
            <span class="font-normal normal-case text-slate-400">({{ $voucher->attachments->count() }})</span>
        </p>
        @if ($voucher->attachments->isEmpty())
            <p class="rounded-lg border border-dashed border-slate-200 px-3 py-2.5 text-center text-[12px] text-slate-400">No supporting documents attached.</p>
        @else
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
        @endif

        @if ($voucherRequest->reason)
        <p class="mb-1.5 mt-5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Reason for {{ $voucherRequest->isCreate() ? 'submission' : ($voucherRequest->isEdit() ? 'edit' : 'deletion') }} (from staff)</p>
        <blockquote class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-[12.5px] italic text-slate-600">
            "{{ $voucherRequest->reason }}"
        </blockquote>
        @endif

        @if ($voucherRequest->isPending())
        {{-- ── Actions ──────────────────────────────────────────────────── --}}
        <div class="mt-5 disburse-actions-row border-t border-gray-100 pt-4">
            <form method="POST" action="{{ route('voucher-requests.approve', $voucherRequest) }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                    <i data-lucide="check" class="h-4 w-4"></i> Approve {{ $voucherRequest->isEdit() ? 'Changes' : ($voucherRequest->isPayment() ? '& Record Payment' : '') }}
                </button>
            </form>

            <button type="button" @click="showRejectNote = ! showRejectNote"
                class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-5 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-100">
                <i data-lucide="x" class="h-4 w-4"></i> Reject {{ $voucherRequest->isEdit() ? 'Changes' : ($voucherRequest->isPayment() ? 'Payment' : '') }}
            </button>

            <span class="text-[11px] text-slate-400">
                @if ($voucherRequest->isEdit()) Rejecting will keep the original approved values.
                @elseif ($voucherRequest->isCreate()) Rejecting marks the voucher as Rejected.
                @elseif ($voucherRequest->isPayment()) Rejecting leaves the voucher unpaid — no payment is posted.
                @else Rejecting leaves the voucher active.
                @endif
            </span>
        </div>

        <form method="POST" action="{{ route('voucher-requests.reject', $voucherRequest) }}" x-show="showRejectNote" x-cloak class="mt-3 rounded-lg border border-rose-200 bg-rose-50/40 p-3">
            @csrf
            <label class="text-[11px] font-semibold uppercase tracking-wide text-rose-700">Note for the requester (optional)</label>
            <textarea name="review_note" rows="2" class="mt-1 block w-full rounded-lg border-rose-200 text-[12.5px] focus:border-rose-400 focus:ring-rose-400" placeholder="Why is this being rejected?"></textarea>
            <div class="mt-2 flex justify-end">
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Confirm Reject</button>
            </div>
        </form>
        @else
        {{-- ── Already reviewed ─────────────────────────────────────────── --}}
        <div class="mt-5 flex flex-wrap items-center gap-2 rounded-lg border px-4 py-3 {{ $voucherRequest->isRejected() ? 'border-rose-200 bg-rose-50/40' : 'border-emerald-200 bg-emerald-50/40' }}">
            <i data-lucide="{{ $voucherRequest->isRejected() ? 'x-circle' : 'check-circle-2' }}"
               class="h-4 w-4 shrink-0 {{ $voucherRequest->isRejected() ? 'text-rose-600' : 'text-emerald-600' }}"></i>
            <p class="text-[12.5px] {{ $voucherRequest->isRejected() ? 'text-rose-700' : 'text-emerald-700' }}">
                {{ $voucherRequest->isRejected() ? 'Rejected' : 'Approved' }}
                by <span class="font-semibold">{{ $voucherRequest->reviewedBy->name ?? '—' }}</span>
                @if ($voucherRequest->reviewed_at)
                    on {{ $voucherRequest->reviewed_at->format('M j, Y \a\t g:i A') }}
                @endif
                @if ($voucherRequest->isRejected() && $voucherRequest->review_note)
                    — "{{ $voucherRequest->review_note }}"
                @endif
            </p>
        </div>
        @endif

    </div>
</div>

@if (count($unchangedFields ?? []) > 0)
<details class="shrink-0 rounded-lg border border-slate-200 bg-white px-4 py-2.5">
    <summary class="cursor-pointer text-[11px] font-semibold text-slate-500">Unchanged fields (hidden by default) — {{ count($unchangedFields) }} fields</summary>
    <div class="mt-2 grid grid-cols-2 gap-2 text-[12px] sm:grid-cols-3">
        @foreach ($unchangedFields as $f)
        <div><span class="text-slate-400">{{ $f['label'] }}:</span> <span class="text-slate-600">{{ $f['value'] }}</span></div>
        @endforeach
    </div>
</details>
@endif

</div>
</x-app-layout>
