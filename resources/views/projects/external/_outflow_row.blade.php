@php
    /** @var \App\Models\ProjectExpense $e */
    $showActions = $showActions ?? true;
@endphp
<tr class="transition-colors hover:bg-slate-50/70 {{ $e->isFromTransfer() ? 'bg-rose-50/20' : '' }}">
    <td class="px-4 py-2.5 tabular-nums text-[13px] text-slate-600">{{ $e->spent_on->format('M j, Y') }}</td>
    <td class="px-4 py-2.5">
        <span class="text-[13px] text-slate-700">{{ $e->description ?? '—' }}</span>
        @if ($e->isFromTransfer())
            <a href="{{ route('transfers.index') }}"
               class="ml-1.5 inline-flex items-center gap-0.5 rounded-full bg-rose-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-rose-800 hover:bg-rose-200"
               title="Auto-created by a transfer">
                <i data-lucide="arrow-left-right" class="h-2.5 w-2.5"></i> transfer
            </a>
        @elseif ($e->isFromVoucher())
            <a href="{{ route('vouchers.index', ['project_id' => $e->project_id]) }}"
               class="ml-1.5 inline-flex items-center gap-0.5 rounded-full bg-blue-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-omet-blue hover:bg-blue-200"
               title="Posted by voucher {{ $e->voucher->voucher_no ?? '' }}">
                <i data-lucide="receipt" class="h-2.5 w-2.5"></i> {{ $e->voucher->voucher_no ?? 'voucher' }}
            </a>
            @if ($e->voucher?->status === 'paid')
                <span class="ml-1 inline-flex items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-emerald-700" title="Voucher fully paid">Full</span>
            @else
                <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-amber-700" title="Voucher partially paid — balance still due">Partial</span>
            @endif
        @endif
    </td>
    <td class="px-4 py-2.5">
        @if ($e->category)
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium capitalize text-slate-600">{{ $e->category }}</span>
        @else
            <span class="text-slate-300">—</span>
        @endif
    </td>
    @if ($showActions)
    <td class="px-4 py-2.5 text-[13px] text-slate-600">{{ $e->vendor_ref ?? '—' }}</td>
    <td class="px-4 py-2.5">
        <span class="block text-[13px] text-slate-700">{{ $e->bankAccount?->name ?? '—' }}</span>
        @if ($e->isFromTransfer() && $e->transfer?->toAccount)
            <span class="block text-[11px] text-slate-400">to {{ $e->transfer->toAccount->name }}@if($e->transfer->toProject) · {{ $e->transfer->toProject->name }}@endif</span>
        @endif
    </td>
    @endif
    <td class="px-4 py-2.5 text-right text-[13px] font-semibold tabular-nums text-red-600">₱{{ number_format($e->amount, 2) }}</td>
    @if ($showActions)
    <td class="px-3 py-2.5 text-right">
        @if ($e->isFromTransfer())
            <span class="inline-flex rounded p-1 text-slate-300" title="Reverse from the Transfers page">
                <i data-lucide="lock" class="h-3 w-3"></i>
            </span>
        @elseif ($e->isFromVoucher())
            <span class="inline-flex rounded p-1 text-slate-300" title="Reverse the payment from Daily Transactions to remove">
                <i data-lucide="lock" class="h-3 w-3"></i>
            </span>
        @else
            <form method="POST" action="{{ route('projects.expenses.destroy', $e) }}" onsubmit="return confirm('Remove this outflow?')">
                @csrf @method('DELETE')
                <button type="submit" class="rounded p-1 text-slate-300 transition hover:bg-red-50 hover:text-red-500">
                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                </button>
            </form>
        @endif
    </td>
    @endif
</tr>
