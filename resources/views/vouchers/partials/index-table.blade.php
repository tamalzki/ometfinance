@php
    $peso = fn ($n) => '₱' . number_format((float) $n, 2);
    $isAccountingUser = auth()->user()->isAccounting();
    $statusTone = [
        'draft'     => 'bg-slate-100 text-slate-600 ring-slate-200',
        'unpaid'    => 'bg-amber-50 text-amber-800 ring-amber-100',
        'partial'   => 'bg-blue-50 text-blue-700 ring-blue-100',
        'pdc'       => 'bg-violet-50 text-violet-700 ring-violet-100',
        'paid'      => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        'cancelled' => 'bg-rose-50 text-rose-600 ring-rose-100',
    ];
@endphp
<div id="disburse-list-fragment" class="disburse-data-grid transition-opacity" data-result-count="{{ $vouchers->total() }}" data-result-mode="{{ $activeSearch ? 'matching' : 'shown' }}">
    <table class="min-w-full">
        <thead class="sticky top-0 z-20">
            <tr>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[108px]">Voucher</th>
                <th class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[96px] sm:table-cell">Date</th>
                <th class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[96px] md:table-cell">Due</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Payee / Particular</th>
                <th class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 md:table-cell">Project</th>
                <th class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 md:table-cell">Category</th>
                <th class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[130px] md:table-cell">Source Doc</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[120px]">Net Amount</th>
                <th class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[110px] md:table-cell">Status</th>
                <th class="sticky right-0 z-30 w-[5.5rem] border-b border-l border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 md:min-w-[15rem]"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($vouchers as $v)
                @php
                    $amountPaid = $v->amountPaid();
                    $balance = $v->balanceDue();
                    $overdue = $v->isOverdue();
                    $notYetApproved = $isAccountingUser && $v->approval_status !== 'approved';
                    $lockReason = $v->isPendingApproval()
                        ? 'Waiting for CFO approval'
                        : ($v->isApprovalRejected() ? 'Rejected by CFO — edit and resubmit, or delete' : null);
                    $pendingReq = $v->pendingRequest();
                    $payLocked = $notYetApproved || $pendingReq !== null;
                    $payLockReason = $notYetApproved ? $lockReason : ($pendingReq ? $pendingReq->typeLabel() . ' is already pending review for this voucher.' : null);
                    // Edit stays open for pending (nothing approved yet to protect) and
                    // rejected (fixing it is the whole point) — only an approved voucher
                    // with another request already in review should be locked.
                    $editLocked = $isAccountingUser && $v->approval_status === 'approved' && $pendingReq !== null;
                    $editLockReason = $editLocked ? $pendingReq->typeLabel() . ' is already pending review for this voucher.' : null;
                    $entryProjects = $v->entries->pluck('project')->filter()->unique('id')->values();
                    $rowProjects   = $entryProjects->isNotEmpty() ? $entryProjects : ($v->project ? collect([$v->project]) : collect());
                    $rowCategories = $v->entries->pluck('category')->filter()->unique('id')->values();
                    $manyCategories = $rowCategories->count() > 2;
                    $payPayload = [
                        'id' => $v->id,
                        'voucher_no' => $v->voucher_no,
                        'payee_name' => $v->payee_name,
                        'balance' => $balance,
                        'source_bank_account_id' => $v->source_bank_account_id,
                        'mode_of_payment' => $v->mode_of_payment,
                    ];
                @endphp
                <tr @class([
                        'group cursor-pointer transition-colors',
                        'hover:bg-slate-50/70' => ! $notYetApproved,
                        'bg-slate-50/80 hover:bg-slate-100/70 grayscale-[35%]' => $notYetApproved,
                        'border-l-2 border-l-amber-300' => $notYetApproved && $v->isPendingApproval(),
                        'border-l-2 border-l-rose-300' => $notYetApproved && $v->isApprovalRejected(),
                    ])
                    @click="window.location = '{{ route('vouchers.show', $v->id) }}'"
                    @if ($notYetApproved) title="{{ $lockReason }}" @endif>
                    <td class="border-b border-slate-100 px-4 py-2.5 align-top whitespace-nowrap">
                        <span class="block text-[12.5px] font-semibold text-slate-700">{{ $v->voucher_no }}</span>
                        @if ($v->source)
                            <span class="mt-1 inline-flex items-center rounded-full px-1 py-px text-[8px] font-semibold uppercase leading-tight tracking-wide ring-1 ring-inset {{ $v->source === 'bgc' ? 'bg-violet-50 text-violet-700 ring-violet-200' : 'bg-teal-50 text-teal-700 ring-teal-200' }}">{{ $v->sourceLabel() }}</span>
                        @endif
                    </td>
                    <td class="hidden border-b border-slate-100 px-4 py-2.5 align-top tabular-nums text-[12px] text-slate-600 whitespace-nowrap sm:table-cell">{{ $v->voucher_date->format('M d, Y') }}</td>
                    <td class="hidden border-b border-slate-100 px-4 py-2.5 align-top tabular-nums text-[12px] whitespace-nowrap md:table-cell {{ $overdue ? 'font-semibold text-rose-600' : 'text-slate-600' }}">
                        {{ $v->due_date?->format('M d, Y') ?? '—' }}
                        @if ($overdue)<span class="block text-[10px] font-medium text-rose-500">overdue</span>@endif
                    </td>
                    <td class="border-b border-slate-100 px-4 py-2.5 align-top">
                        <p class="text-[13px] font-medium text-slate-700">{{ $v->payee_name }}</p>
                        @if ($v->particular)
                            <p class="mt-0.5 max-w-[220px] truncate text-[11px] text-slate-400">{{ $v->particular }}</p>
                        @endif
                    </td>
                    <td class="hidden border-b border-slate-100 px-4 py-2.5 align-top text-[12.5px] text-slate-600 md:table-cell">
                        @if ($rowProjects->isNotEmpty())
                            <div class="flex flex-wrap gap-1">
                                @foreach ($rowProjects as $p)
                                    @if ($isAccountingUser)
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600">
                                            {{ $p->name }}
                                        </span>
                                    @else
                                        <a href="{{ route('projects.show', $p) }}" @click.stop
                                           class="inline-flex items-center rounded-md bg-omet-blue/5 px-1.5 py-0.5 text-[11px] font-medium text-omet-blue hover:underline">
                                            {{ $p->name }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="hidden border-b border-slate-100 px-4 py-2.5 align-top md:table-cell">
                        @if ($rowCategories->isNotEmpty())
                            <div class="flex flex-wrap gap-1">
                                @foreach ($rowCategories as $cat)
                                    <span class="inline-flex items-center whitespace-nowrap rounded-md bg-slate-100 px-1.5 py-0.5 font-medium text-slate-600 {{ $manyCategories ? 'text-[9.5px]' : 'text-[11px]' }}">
                                        {{ $cat->fullLabel() }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="hidden border-b border-slate-100 px-4 py-2.5 align-top whitespace-nowrap md:table-cell">
                        @if ($v->source_document_type)
                            <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-1.5 py-0.5 text-[10.5px] font-medium text-amber-700 ring-1 ring-amber-100">
                                <i data-lucide="{{ $v->sourceDocumentIcon() }}" class="h-3 w-3"></i>
                                {{ $v->sourceDocumentLabel() }}
                            </span>
                            @if ($v->po_number)
                                <span class="mt-1 block text-[10px] text-slate-500">{{ $v->sourceDocumentNumberLabel() }}: <span class="font-medium text-slate-600">{{ $v->po_number }}</span></span>
                            @endif
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="border-b border-slate-100 px-4 py-2.5 align-top text-right text-[12.5px] font-semibold tabular-nums text-omet-navy whitespace-nowrap">{{ $peso($v->amount_payable) }}</td>
                    <td class="hidden border-b border-slate-100 px-4 py-2.5 align-top md:table-cell">
                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $statusTone[$v->status] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $v->statusLabel() }}</span>
                        @if ($v->isPendingApproval())
                            <span class="mt-1 block w-fit rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-amber-100">For Approval</span>
                        @elseif ($v->isApprovalRejected())
                            <span class="mt-1 block w-fit cursor-help rounded-md bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-600 ring-1 ring-rose-100"
                                  title="{{ $v->latestRequest()?->review_note ?: 'No reason was provided.' }}">Rejected — hover for reason</span>
                        @elseif ($pendingReq)
                            <span class="mt-1 block w-fit rounded-md bg-violet-50 px-1.5 py-0.5 text-[10px] font-semibold text-violet-700 ring-1 ring-violet-100">{{ $pendingReq->typeLabel() }}</span>
                        @endif
                    </td>
                    <td class="sticky right-0 z-10 border-b border-l border-slate-200 bg-white px-3 py-2.5 align-middle group-hover:bg-slate-50" @click.stop>
                        <div class="disburse-row-actions">
                            @if ($v->isOpen())
                                <button type="button"
                                        @if ($payLocked) disabled aria-label="{{ $payLockReason }}" title="{{ $payLockReason }}"
                                        @else aria-label="Pay voucher {{ $v->voucher_no }}" title="Pay" @click="openPay({{ \Illuminate\Support\Js::from($payPayload) }})" @endif
                                        @class([
                                            'disburse-row-action',
                                            'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' => ! $payLocked,
                                            'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400' => $payLocked,
                                        ])>
                                    <i data-lucide="banknote" class="h-3.5 w-3.5 pointer-events-none"></i>
                                    <span class="disburse-row-action-label hidden md:inline">Pay</span>
                                </button>
                            @endif
                            @if ($editLocked)
                                <span title="{{ $editLockReason }}" aria-label="{{ $editLockReason }}"
                                      class="disburse-row-action cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400">
                                    <i data-lucide="pencil" class="h-3.5 w-3.5 pointer-events-none"></i>
                                    <span class="disburse-row-action-label hidden md:inline">Edit</span>
                                </span>
                            @else
                                <a href="{{ route('vouchers.edit', $v) }}" @click.stop
                                   aria-label="Edit voucher {{ $v->voucher_no }}" title="Edit"
                                   class="disburse-row-action border-slate-200 bg-white text-slate-600 hover:bg-slate-50">
                                    <i data-lucide="pencil" class="h-3.5 w-3.5 pointer-events-none"></i>
                                    <span class="disburse-row-action-label hidden md:inline">Edit</span>
                                </a>
                            @endif
                            @if ($v->isOpen() && $v->payments->isEmpty())
                                <form method="POST" action="{{ route('vouchers.cancel', $v->id) }}"
                                      onsubmit="return confirm('Cancel voucher {{ $v->voucher_no }}? It will be excluded from payables and can be reactivated later.');" class="inline-flex shrink-0">
                                    @csrf
                                    <button type="submit" @if ($notYetApproved) disabled aria-label="{{ $lockReason }}" title="{{ $lockReason }}" @else aria-label="Cancel voucher {{ $v->voucher_no }}" title="Cancel" @endif
                                            @class([
                                                'disburse-row-action',
                                                'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100' => ! $notYetApproved,
                                                'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400' => $notYetApproved,
                                            ])>
                                        <i data-lucide="ban" class="h-3.5 w-3.5 pointer-events-none"></i>
                                        <span class="disburse-row-action-label hidden md:inline">Cancel</span>
                                    </button>
                                </form>
                            @elseif ($v->status === 'cancelled')
                                <form method="POST" action="{{ route('vouchers.reactivate', $v->id) }}" class="inline-flex shrink-0">
                                    @csrf
                                    <button type="submit" @if ($notYetApproved) disabled aria-label="{{ $lockReason }}" title="{{ $lockReason }}" @else aria-label="Reactivate voucher {{ $v->voucher_no }}" title="Reactivate" @endif
                                            @class([
                                                'disburse-row-action',
                                                'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' => ! $notYetApproved,
                                                'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400' => $notYetApproved,
                                            ])>
                                        <i data-lucide="rotate-ccw" class="h-3.5 w-3.5 pointer-events-none"></i>
                                        <span class="disburse-row-action-label hidden md:inline">Reactivate</span>
                                    </button>
                                </form>
                            @endif
                            @php
                                $needsDeleteReason = $isAccountingUser && $v->approval_status === 'approved';
                                $deleteLocked = $isAccountingUser && $v->isPendingApproval();
                            @endphp
                            @if ($deleteLocked)
                                <span title="Still awaiting CFO approval — cannot be deleted yet."
                                      aria-label="Still awaiting CFO approval — cannot be deleted yet."
                                      class="disburse-row-action cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5 pointer-events-none"></i>
                                </span>
                            @else
                                <form method="POST" action="{{ route('vouchers.destroy', $v->id) }}" x-data="{ reason: '' }"
                                      @if ($needsDeleteReason)
                                      @submit="reason = prompt('Reason for requesting deletion of voucher {{ $v->voucher_no }} (required):') || ''; if (! reason.trim()) { $event.preventDefault(); }"
                                      @else
                                      onsubmit="return confirm('Delete voucher {{ $v->voucher_no }}? Any posted payments will be reversed.');"
                                      @endif
                                      class="inline-flex shrink-0">
                                    @csrf @method('DELETE')
                                    @if ($needsDeleteReason)<input type="hidden" name="reason" x-bind:value="reason">@endif
                                    <button type="submit"
                                            aria-label="{{ $needsDeleteReason ? 'Request deletion of voucher ' . $v->voucher_no : 'Delete voucher ' . $v->voucher_no }}"
                                            title="{{ $needsDeleteReason ? 'Request deletion' : 'Delete' }}"
                                            class="disburse-row-action border-red-200 bg-red-50 text-red-600 hover:bg-red-100">
                                        <i data-lucide="trash-2" class="h-3.5 w-3.5 pointer-events-none"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-6 py-14 text-center">
                        <i data-lucide="receipt" class="mx-auto mb-2 h-8 w-8 text-slate-200"></i>
                        <p class="text-xs text-slate-400">No transactions yet. Use <span class="font-semibold text-omet-blue">Add Voucher</span>.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <x-pagination-simple :paginator="$vouchers" />
</div>
