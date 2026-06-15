@php
    /** @var \App\Models\ProjectCollection $c */
    $showActions = $showActions ?? true;
    $showType    = $showType ?? false;
    $filterable  = $filterable ?? false;

    $isBorrowed = $c->isFromTransfer();
    $rowType    = $isBorrowed ? 'funding' : 'collection';

    // Labels depend on the project kind: manual rows on external projects are
    // client collections; on in-house projects they are legacy manual entries.
    $isExternalProject = $project->isExternal();
    $typeLabel = $isBorrowed
        ? ($isExternalProject ? 'Borrowed' : 'Funding')
        : ($isExternalProject ? 'Collection' : 'Manual');
    $typeIcon = $isBorrowed ? 'arrow-left-right' : ($isExternalProject ? 'hand-coins' : 'pencil-line');
    $typeClasses = $isBorrowed
        ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-200'
        : ($isExternalProject
            ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'
            : 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200');
@endphp
<tr @if ($filterable) x-show="inflowFilter === 'all' || inflowFilter === '{{ $rowType }}'" @endif
    class="transition-colors hover:bg-slate-50/70 {{ $isBorrowed ? 'bg-indigo-50/20' : '' }}">
    <td class="px-4 py-2.5 tabular-nums text-[13px] text-slate-600">{{ $c->collected_on->format('M j, Y') }}</td>
    @if ($showType)
    <td class="px-4 py-2.5">
        @can('manage-financials')
            @if ($isBorrowed)
                <a href="{{ route('transfers.index') }}" title="Booked as a transfer — manage from the Transfers page"
                   class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold transition-colors {{ $typeClasses }} hover:bg-indigo-100">
                    <i data-lucide="{{ $typeIcon }}" class="h-3 w-3"></i> {{ $typeLabel }}
                </a>
            @else
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $typeClasses }}">
                    <i data-lucide="{{ $typeIcon }}" class="h-3 w-3"></i> {{ $typeLabel }}
                </span>
            @endif
        @else
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $typeClasses }}">
                <i data-lucide="{{ $typeIcon }}" class="h-3 w-3"></i> {{ $typeLabel }}
            </span>
        @endcan
    </td>
    @endif
    <td class="px-4 py-2.5">
        <span class="text-[13px] text-slate-700">{{ $c->reference ?? '—' }}</span>
        @if ($isBorrowed && ! $showType)
            <span class="ml-1.5 inline-flex items-center gap-0.5 rounded-full bg-indigo-50 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-indigo-700 ring-1 ring-inset ring-indigo-200"
                  title="Funded by a transfer from another account">
                <i data-lucide="arrow-left-right" class="h-2.5 w-2.5"></i> {{ $typeLabel }}
            </span>
        @endif
    </td>
    <td class="px-4 py-2.5">
        <span class="block text-[13px] text-slate-700">{{ $c->bankAccount?->name ?? '—' }}</span>
        @if ($isBorrowed && $c->transfer?->fromAccount)
            <span class="block text-[11px] text-slate-400">from {{ $c->transfer->fromAccount->name }}@if($c->transfer->fromProject) · {{ $c->transfer->fromProject->name }}@endif</span>
        @endif
    </td>
    <td class="px-4 py-2.5 text-right text-[13px] font-semibold tabular-nums text-emerald-700">₱{{ number_format($c->amount, 2) }}</td>
    @if ($showActions)
    <td class="px-4 py-2.5 text-[12px] text-slate-500">{{ $c->notes ?? '' }}</td>
    <td class="px-3 py-2.5 text-right">
        @if ($isBorrowed)
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
