<x-app-layout page-title="Voucher Approvals">
@php
    $peso = fn ($n) => '₱' . number_format((float) $n, 2);

    $typeTone = [
        'create' => 'bg-amber-50 text-amber-700 ring-amber-100',
        'edit'   => 'bg-violet-50 text-violet-700 ring-violet-100',
        'delete' => 'bg-rose-50 text-rose-600 ring-rose-100',
    ];

    $tabs = [
        ''       => 'All',
        'create' => 'For Approval',
        'edit'   => 'Edit Request',
        'delete' => 'Delete Request',
    ];
@endphp

<div class="flex min-h-0 min-w-0 flex-1 flex-col gap-2.5">

@if (session('success'))
    <div class="flex shrink-0 items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs font-medium text-green-800">
        <i data-lucide="check-circle-2" class="h-3.5 w-3.5 shrink-0 text-green-600"></i>
        {{ session('success') }}
    </div>
@endif

<div class="flex shrink-0 flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h1 class="text-xl font-bold tracking-tight text-omet-navy">Voucher Approvals</h1>
        <p class="text-xs text-slate-500">Requests submitted by Accounting Staff, awaiting your review.</p>
    </div>
</div>

{{-- ── Summary cards — 3 inline ───────────────────────────────────────── --}}
<div class="flex shrink-0 flex-row flex-nowrap gap-2.5">
    @foreach ([
        ['key' => 'create', 'label' => 'For Approval', 'icon' => 'file-plus', 'tone' => 'amber'],
        ['key' => 'edit',   'label' => 'Edit Request', 'icon' => 'pencil',    'tone' => 'violet'],
        ['key' => 'delete', 'label' => 'Delete Request', 'icon' => 'trash-2', 'tone' => 'rose'],
    ] as $card)
    @php
        $active = ($activeType ?? '') === $card['key'];
        $tone = [
            'amber'  => ['count' => 'text-amber-700',  'icon' => 'bg-amber-50 text-amber-600',  'active' => 'border-amber-200 ring-1 ring-amber-200'],
            'violet' => ['count' => 'text-violet-700', 'icon' => 'bg-violet-50 text-violet-600', 'active' => 'border-violet-200 ring-1 ring-violet-200'],
            'rose'   => ['count' => 'text-rose-600',   'icon' => 'bg-rose-50 text-rose-600',    'active' => 'border-rose-200 ring-1 ring-rose-200'],
        ][$card['tone']];
    @endphp
    <a href="{{ route('voucher-requests.index', ['type' => $card['key']]) }}"
        class="flex min-w-0 flex-1 items-center gap-2 rounded-lg border bg-white px-3 py-2 shadow-sm transition hover:shadow-md {{ $active ? $tone['active'] : 'border-gray-100' }}">
        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md {{ $tone['icon'] }}">
            <i data-lucide="{{ $card['icon'] }}" class="h-3.5 w-3.5"></i>
        </span>
        <p class="min-w-0 truncate text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $card['label'] }}</p>
        <p class="ml-auto shrink-0 text-sm font-bold tabular-nums {{ $tone['count'] }}">{{ $counts[$card['key']] }}</p>
    </a>
    @endforeach
</div>

{{-- ── Filter tabs ──────────────────────────────────────────────────────── --}}
<nav class="flex shrink-0 overflow-x-auto border-b border-gray-200">
    @foreach ($tabs as $key => $label)
    <a href="{{ route('voucher-requests.index', $key ? ['type' => $key] : []) }}"
        @class([
            '-mb-px flex items-center gap-1.5 whitespace-nowrap px-4 py-2 text-sm transition-colors duration-150',
            'border-b-2 border-omet-blue text-omet-blue font-semibold' => ($activeType ?? '') === $key,
            'border-b-2 border-transparent text-gray-500 hover:text-omet-navy hover:border-gray-300' => ($activeType ?? '') !== $key,
        ])>
        {{ $label }}
    </a>
    @endforeach
</nav>

{{-- ── Table ────────────────────────────────────────────────────────────── --}}
<div class="data-grid min-h-0 min-w-0 flex-1 overflow-auto">
    <table class="min-w-full">
        <thead class="sticky top-0 z-20">
            <tr>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Type</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Voucher</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Payee</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Requested by</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Requested</th>
                <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Review</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
            @forelse ($requests as $req)
            <tr class="transition-colors hover:bg-slate-50/50">
                <td class="px-4 py-2.5">
                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $typeTone[$req->type] ?? '' }}">{{ $req->typeLabel() }}</span>
                </td>
                <td class="px-4 py-2.5 text-[13px] font-semibold text-slate-800">{{ $req->voucher?->voucher_no ?? '— deleted —' }}</td>
                <td class="px-4 py-2.5 text-[13px] text-slate-700">{{ $req->voucher?->payee_name ?? '—' }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums text-[13px] font-semibold text-omet-navy">{{ $req->voucher ? $peso($req->voucher->amount_payable) : '—' }}</td>
                <td class="px-4 py-2.5 text-[13px] text-slate-600">{{ $req->requestedBy->name ?? '—' }}</td>
                <td class="px-4 py-2.5 text-[12px] text-slate-500">{{ $req->created_at->format('M j, Y g:i A') }}</td>
                <td class="px-4 py-2.5 text-right">
                    <a href="{{ route('voucher-requests.show', $req) }}"
                       class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 shadow-sm transition hover:border-omet-blue hover:text-omet-blue">
                        <i data-lucide="eye" class="h-3 w-3"></i> Review
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-6 py-14 text-center">
                    <i data-lucide="inbox" class="mx-auto mb-2 h-8 w-8 text-slate-200"></i>
                    <p class="text-sm text-slate-500">Nothing pending review.</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

</div>
</x-app-layout>
