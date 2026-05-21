@php
    /** @var \App\Models\ProjectCollection $c */
    $showActions = $showActions ?? true;
@endphp
<tr class="transition-colors hover:bg-slate-50/70 {{ $c->isFromTransfer() ? 'bg-emerald-50/20' : '' }}">
    <td class="px-4 py-2.5 tabular-nums text-[13px] text-slate-600">{{ $c->collected_on->format('M j, Y') }}</td>
    <td class="px-4 py-2.5">
        <span class="text-[13px] text-slate-700">{{ $c->reference ?? '—' }}</span>
        @if ($c->isFromTransfer())
            <a href="{{ route('transfers.index') }}"
               class="ml-1.5 inline-flex items-center gap-0.5 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-emerald-800 hover:bg-emerald-200"
               title="Auto-created by a transfer">
                <i data-lucide="arrow-left-right" class="h-2.5 w-2.5"></i> transfer
            </a>
        @endif
    </td>
    <td class="px-4 py-2.5">
        <span class="block text-[13px] text-slate-700">{{ $c->bankAccount?->name ?? '—' }}</span>
        @if ($c->isFromTransfer() && $c->transfer?->fromAccount)
            <span class="block text-[11px] text-slate-400">from {{ $c->transfer->fromAccount->name }}@if($c->transfer->fromProject) · {{ $c->transfer->fromProject->name }}@endif</span>
        @endif
    </td>
    <td class="px-4 py-2.5 text-right text-[13px] font-semibold tabular-nums text-emerald-700">₱{{ number_format($c->amount, 2) }}</td>
    @if ($showActions)
    <td class="px-4 py-2.5 text-[12px] text-slate-500">{{ $c->notes ?? '' }}</td>
    <td class="px-3 py-2.5 text-right">
        @if ($c->isFromTransfer())
            <span class="inline-flex rounded p-1 text-slate-300" title="Reverse from the Transfers page">
                <i data-lucide="lock" class="h-3 w-3"></i>
            </span>
        @else
            <form method="POST" action="{{ route('projects.collections.destroy', $c) }}" onsubmit="return confirm('Remove this inflow?')">
                @csrf @method('DELETE')
                <button type="submit" class="rounded p-1 text-slate-300 transition hover:bg-red-50 hover:text-red-500">
                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                </button>
            </form>
        @endif
    </td>
    @endif
</tr>
