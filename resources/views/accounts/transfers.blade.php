<x-app-layout page-title="Transfers">
<div class="space-y-4" x-data="{ q: '' }">

{{-- Flash --}}
@if (session('success'))
    <div class="flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
        <i data-lucide="check-circle-2" class="h-4 w-4 shrink-0 text-green-600"></i>
        {{ session('success') }}
    </div>
@endif

{{-- ── Top tab bar (pill style, matches accounts.overall) ─────────────────── --}}
<div class="flex flex-wrap items-center gap-2">
    <a href="{{ route('accounts.overall') }}"
       class="rounded-full border border-gray-200 bg-white px-4 py-1.5 text-[13px] font-medium leading-none text-gray-600 transition hover:bg-gray-50 hover:border-gray-300">
        All entities
    </a>
    @foreach ($entities as $tab)
        <a href="{{ route('accounts.entity', $tab->slug) }}"
           class="rounded-full border border-gray-200 bg-white px-4 py-1.5 text-[13px] font-medium leading-none text-gray-600 transition hover:bg-gray-50 hover:border-gray-300">
            {{ $tab->name }}
        </a>
    @endforeach
    <a href="{{ route('accounts.transfers.index') }}"
       class="ml-auto flex items-center gap-1.5 rounded-full border border-[#185FA5] bg-[#185FA5] px-4 py-1.5 text-[13px] font-medium leading-none text-white shadow-sm">
        <i data-lucide="history" class="h-3.5 w-3.5"></i>
        Transfers history
    </a>
</div>

{{-- ── Stat cards ─────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-[11px] font-medium uppercase tracking-wider text-gray-500">Transfers shown</p>
        <p class="mt-1.5 text-xl font-semibold tracking-tight text-gray-900 tabular-nums">
            {{ $transfers->count() }}
        </p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-[11px] font-medium uppercase tracking-wider text-gray-500">Total moved</p>
        <p class="mt-1.5 text-xl font-semibold tracking-tight text-gray-900 tabular-nums">
            ₱{{ number_format($totalAmount, 2) }}
        </p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-[11px] font-medium uppercase tracking-wider text-gray-500">Date range</p>
        <p class="mt-1.5 truncate text-[13px] font-medium text-gray-700 tabular-nums">
            @if ($from || $to)
                {{ $from ? \Illuminate\Support\Carbon::parse($from)->format('M d, Y') : 'All time' }}
                → {{ $to ? \Illuminate\Support\Carbon::parse($to)->format('M d, Y') : 'today' }}
            @else
                All time
            @endif
        </p>
    </div>
</div>

{{-- ── Filter bar ────────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
    <form method="GET" action="{{ route('accounts.transfers.index') }}"
          class="flex flex-wrap items-end gap-3">
        <div>
            <label class="mb-1 block text-[11px] font-medium text-gray-500">From date</label>
            <input type="date" name="from" value="{{ $from ?? '' }}"
                   class="h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-[12px] text-gray-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
        </div>
        <div>
            <label class="mb-1 block text-[11px] font-medium text-gray-500">To date</label>
            <input type="date" name="to" value="{{ $to ?? '' }}"
                   class="h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-[12px] text-gray-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
        </div>
        <button type="submit"
                class="h-9 rounded-lg bg-omet-blue px-4 text-[12px] font-semibold text-white shadow-sm transition hover:bg-omet-lightblue">
            Apply filter
        </button>
        @if ($from || $to)
            <a href="{{ route('accounts.transfers.index') }}"
               class="flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 text-[12px] font-semibold text-gray-600 transition hover:bg-gray-50">
                <i data-lucide="x" class="h-3.5 w-3.5"></i> Clear
            </a>
        @endif
    </form>

    <div class="ml-auto">
        <label class="mb-1 block text-[11px] font-medium text-gray-500">Quick search</label>
        <div class="relative">
            <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400">
                <i data-lucide="search" class="h-3.5 w-3.5"></i>
            </span>
            <input type="text" x-model="q"
                   placeholder="Search account or memo…"
                   class="h-9 w-64 rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-[12px] text-gray-700 placeholder-gray-400 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/10">
        </div>
    </div>
</div>

{{-- ── Transfers table ──────────────────────────────────────────────────── --}}
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <table class="min-w-full border-separate border-spacing-0 text-[12px]">
        <thead>
            <tr class="bg-slate-50">
                <th class="border-b border-r border-slate-200 px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500 w-[110px]">Date</th>
                <th class="border-b border-r border-slate-200 px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">From</th>
                <th class="border-b border-r border-slate-200 px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">To</th>
                <th class="border-b border-r border-slate-200 px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Memo</th>
                <th class="border-b border-r border-slate-200 px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-gray-500 w-[140px]">Amount</th>
                <th class="border-b border-slate-200 px-3 py-2 text-center text-[10px] font-semibold uppercase tracking-wider text-gray-400 w-[60px]"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transfers as $i => $transfer)
                @php
                    $haystack = strtolower(implode(' ', array_filter([
                        $transfer->fromAccount?->name,
                        $transfer->fromAccount?->entity?->name,
                        $transfer->toAccount?->name,
                        $transfer->toAccount?->entity?->name,
                        $transfer->memo,
                    ])));
                @endphp
                <tr class="group transition hover:bg-blue-50/30 {{ $i % 2 === 0 ? 'bg-white' : 'bg-slate-50/40' }}"
                    x-show="q.trim() === '' || @js($haystack).includes(q.trim().toLowerCase())">
                    <td class="border-b border-r border-slate-100 px-4 py-2 text-[11px] tabular-nums text-gray-500 whitespace-nowrap">
                        {{ $transfer->date->format('M d, Y') }}
                    </td>
                    <td class="border-b border-r border-slate-100 px-3 py-2">
                        <p class="text-[12px] font-medium text-gray-700">{{ $transfer->fromAccount?->name ?? '—' }}</p>
                        <p class="text-[10px] text-gray-400">{{ $transfer->fromAccount?->entity?->name }}</p>
                    </td>
                    <td class="border-b border-r border-slate-100 px-3 py-2">
                        <p class="text-[12px] font-medium text-gray-700">{{ $transfer->toAccount?->name ?? '—' }}</p>
                        <p class="text-[10px] text-gray-400">{{ $transfer->toAccount?->entity?->name }}</p>
                    </td>
                    <td class="border-b border-r border-slate-100 px-3 py-2 text-[12px] text-gray-600">
                        @if ($transfer->memo)
                            {{ $transfer->memo }}
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="border-b border-r border-slate-100 px-3 py-2 text-right text-[12px] font-semibold tabular-nums text-omet-navy">
                        ₱{{ number_format($transfer->amount, 2) }}
                    </td>
                    <td class="border-b border-slate-100 px-2 py-2 text-center">
                        <form method="POST"
                              action="{{ route('accounts.transfers.destroy', $transfer->id) }}?redirect_to=transfers"
                              onsubmit="return confirm('Reverse this transfer? Both legs will be removed.');"
                              class="inline-flex">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="rounded p-1 text-gray-300 transition hover:bg-red-50 hover:text-red-600"
                                    title="Reverse transfer">
                                <i data-lucide="undo-2" class="h-3.5 w-3.5"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-14 text-center">
                        <i data-lucide="arrow-left-right" class="mx-auto mb-2 h-8 w-8 text-gray-200"></i>
                        <p class="text-xs text-gray-400">
                            @if ($from || $to)
                                No transfers in this date range.
                            @else
                                No transfers yet.
                            @endif
                        </p>
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($transfers->isNotEmpty())
            <tfoot>
                <tr class="bg-slate-50">
                    <td colspan="4" class="border-t border-r border-slate-200 px-4 py-2 text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                        Total
                    </td>
                    <td class="border-t border-r border-slate-200 px-3 py-2 text-right text-[12px] font-semibold tabular-nums text-omet-navy">
                        ₱{{ number_format($totalAmount, 2) }}
                    </td>
                    <td class="border-t border-slate-200"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

</div>
</x-app-layout>
