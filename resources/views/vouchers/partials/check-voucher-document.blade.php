@php
    use App\Support\AmountInWords;

    $fmtDate   = fn ($d) => $d ? $d->format('j-M-y') : '—';
    $fmtAmount = fn ($n) => number_format((float) $n, 2);

    $totalDebit  = $voucher->entries->where('entry_type', 'debit')->sum(fn ($e) => (float) $e->amount);
    $totalCredit = $voucher->entries->where('entry_type', 'credit')->sum(fn ($e) => (float) $e->amount);
    $isBalanced  = abs($totalDebit - $totalCredit) < 0.005;

    // Amount actually disbursed — the "Cash in Bank" credit line, not the
    // full debit total, since other credits (e.g. WHT) are withheld, not paid out.
    $cashAmount = $voucher->entries
        ->where('entry_type', 'credit')
        ->filter(fn ($e) => str_contains(strtolower($e->category?->name ?? ''), 'cash in bank'))
        ->sum(fn ($e) => (float) $e->amount);

    $displayAmount = $cashAmount > 0
        ? $cashAmount
        : ($isBalanced && $totalDebit > 0 ? $totalDebit : (float) $voucher->amount_payable);

    $termsLabel = '—';
    if ($voucher->due_date) {
        $termsLabel = $voucher->due_date->isSameDay($voucher->voucher_date)
            ? 'ON DATE'
            : strtoupper($voucher->due_date->format('j-M-y'));
    }

    $hasPayment = $voucher->payments->isNotEmpty();

    $checkPayment = $voucher->payments
        ->filter(fn ($p) => $p->check_no || $p->mode === 'check')
        ->sortByDesc(fn ($p) => $p->paid_on?->timestamp ?? 0)
        ->first();

    $lastPayment = $voucher->payments->sortByDesc(fn ($p) => $p->paid_on?->timestamp ?? 0)->first();

    $bankCheckNoLabel = $hasPayment ? ($voucher->sourceBankAccount?->name ?? '') : '';
    $checkDateLabel   = $hasPayment
        ? ($checkPayment?->check_date ? $fmtDate($checkPayment->check_date) : ($lastPayment?->paid_on ? $fmtDate($lastPayment->paid_on) : ''))
        : '';

    $entryAccountInline = function ($entry) {
        $parts = [strtoupper($entry->category?->fullLabel() ?: '—')];
        if ($entry->project) {
            $parts[] = strtoupper($entry->project->name . ($entry->project->code ? ' (' . $entry->project->code . ')' : ''));
        }
        if ($entry->description) {
            $parts[] = strtoupper($entry->description);
        }

        return implode('  —  ', $parts);
    };

    $defaultAddress = 'RS Building, Sta Ana Ave., Poblacion District, Davao City';
@endphp

<div class="check-voucher-doc mx-auto max-w-6xl rounded-xl border border-slate-300 bg-white p-8 shadow-sm print:max-w-none print:rounded-none print:border-0 print:p-0 print:shadow-none">

    {{-- Header --}}
    <div class="mb-6 flex items-start justify-between gap-6 border-b border-slate-300 pb-5">
        <div class="w-36 shrink-0"></div>

        <div class="flex-1 text-center">
            <p class="text-[17px] font-bold tracking-tight">
                <span class="text-red-600">One</span><span class="text-blue-700">Mark</span>
                <span class="text-slate-800"> Engineering Technologies</span><sup class="text-[9px]">™</sup>
            </p>
            <p class="mt-2 whitespace-nowrap text-[9px] leading-relaxed text-slate-600">
                DOOR 10 EBRO PELAYO BLDG., JUAN LUNA ST., POBLACION DISTRICT, DAVAO CITY 8000
            </p>
        </div>

        <div class="w-36 shrink-0 text-right">
            <div class="inline-block rounded border border-slate-400 px-4 py-2 text-center">
                <p class="text-[10px] font-bold tracking-wider text-slate-700">RECORDED</p>
                <p class="mt-2 flex items-baseline justify-end gap-1.5 text-[9px] text-slate-500">
                    <span>Date:</span>
                    <span class="inline-block min-w-[72px] border-b border-slate-400 pb-0.5">{{ $fmtDate($voucher->voucher_date) }}</span>
                </p>
            </div>
        </div>
    </div>

    {{-- Voucher meta --}}
    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1 space-y-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-800">Payee:</p>
                <p class="mt-0.5 border-b border-slate-800 pb-0.5 text-[13px] font-semibold uppercase text-slate-900">
                    {{ $voucher->payee_name }}
                </p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-800">Address:</p>
                <div class="mt-0.5 min-h-[2.75rem] rounded border border-slate-400 px-2 py-1.5 text-[11px] leading-snug text-slate-700">
                    {{ $defaultAddress }}
                </div>
            </div>
        </div>

        <div class="w-full shrink-0 sm:w-64">
            <p class="mb-2 whitespace-nowrap text-right text-[12px] font-bold uppercase tracking-wide text-slate-900">
                Check Voucher: {{ $voucher->voucher_no }}
            </p>
            <table class="w-full text-[11px]">
                <tbody>
                    <tr>
                        <td class="py-0.5 pr-2 text-right font-bold uppercase text-slate-800">Date:</td>
                        <td class="border-b border-slate-800 py-0.5 text-right tabular-nums text-slate-900">{{ $fmtDate($voucher->voucher_date) }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 pr-2 text-right font-bold uppercase text-slate-800">Amount Due:</td>
                        <td class="border-b border-slate-800 py-0.5 text-right font-semibold tabular-nums text-slate-900">₱{{ $fmtAmount($displayAmount) }}</td>
                    </tr>
                    <tr>
                        <td class="py-0.5 pr-2 text-right font-bold uppercase text-slate-800">Terms:</td>
                        <td class="border-b border-slate-800 py-0.5 text-right uppercase text-slate-900">{{ $termsLabel }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Memo --}}
    <div class="mb-4">
        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-800">Memo:</p>
        <div class="mt-0.5 min-h-[3rem] rounded border border-slate-400 px-2 py-1.5 text-[11px] leading-snug text-slate-800">
            @php
                $memoParts = array_filter([
                    $voucher->particular,
                    $voucher->po_number ? 'PO: ' . $voucher->po_number : null,
                    $voucher->reference ? 'Ref: ' . $voucher->reference : null,
                ]);
            @endphp
            {{ $memoParts ? implode(' · ', $memoParts) : '—' }}
        </div>
    </div>

    {{-- Accounting table --}}
    <div class="overflow-x-auto rounded border border-slate-800">
        <table class="w-full border-collapse text-[11px]">
            <thead>
                <tr class="border-b border-slate-800 bg-white">
                    <th class="border-r border-slate-800 px-3 py-1.5 text-left font-bold uppercase tracking-wide text-slate-900">Account</th>
                    <th class="w-24 border-r border-slate-800 px-2 py-1.5 text-right font-bold uppercase tracking-wide text-slate-900">Debit</th>
                    <th class="w-24 px-2 py-1.5 text-right font-bold uppercase tracking-wide text-slate-900">Credit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($voucher->entries as $entry)
                    <tr class="border-b border-slate-300 align-top">
                        <td class="whitespace-nowrap border-r border-slate-300 px-3 py-2 font-medium uppercase text-slate-900">
                            {{ $entryAccountInline($entry) }}
                        </td>
                        <td class="border-r border-slate-300 px-2 py-2 text-right tabular-nums text-slate-900">
                            @if ($entry->entry_type === 'debit')
                                {{ $fmtAmount($entry->amount) }}
                            @endif
                        </td>
                        <td class="px-2 py-2 text-right tabular-nums text-slate-900">
                            @if ($entry->entry_type === 'credit')
                                {{ $fmtAmount($entry->amount) }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="border-b border-slate-300">
                        <td colspan="3" class="px-2 py-6 text-center text-slate-400">No accounting entries recorded.</td>
                    </tr>
                @endforelse

                @if ($voucher->entries->isNotEmpty())
                    <tr class="bg-slate-100 font-semibold">
                        <td class="border-r border-t border-slate-800 px-3 py-1.5 text-right uppercase text-slate-900">Total</td>
                        <td class="border-r border-t border-slate-800 px-2 py-1.5 text-right tabular-nums text-slate-900">
                            {{ $fmtAmount($totalDebit) }}
                        </td>
                        <td class="border-t border-slate-800 px-2 py-1.5 text-right tabular-nums text-slate-900">
                            {{ $fmtAmount($totalCredit) }}
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- Payment summary below table --}}
    <div class="mt-5 space-y-3 text-[11px] text-slate-800">
        <div class="flex flex-wrap gap-x-10 gap-y-1.5">
            <p class="flex items-baseline gap-1.5">
                <span class="font-bold uppercase">Bank / Check No.:</span>
                <span class="inline-block min-w-[130px] border-b border-slate-500 px-1.5 pb-0.5">
                    {{ $bankCheckNoLabel }}
                </span>
            </p>
            <p class="flex items-baseline gap-1.5">
                <span class="font-bold uppercase">Check Date:</span>
                <span class="inline-block min-w-[110px] border-b border-slate-500 px-1.5 pb-0.5">
                    {{ $checkDateLabel }}
                </span>
            </p>
        </div>

        <p class="leading-relaxed">
            Received from OneMark Engineering Technologies, the amount of Php
            <span class="ml-1 inline-block min-w-[100px] border-b border-slate-800 px-1.5 pb-0.5 font-semibold tabular-nums">{{ $fmtAmount($displayAmount) }}</span>
        </p>

        <p class="leading-relaxed">
            <span class="inline-block min-w-full border-b border-slate-800 px-1.5 pb-0.5 font-semibold uppercase tracking-wide">
                {{ AmountInWords::peso($displayAmount) }}
            </span>
            <span class="mt-1 block text-slate-700">as payment for the particular shown above.</span>
        </p>
    </div>
</div>

<style>
    @media print {
        body * { visibility: hidden; }
        .check-voucher-doc, .check-voucher-doc * { visibility: visible; }
        .check-voucher-doc {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
    }
</style>
