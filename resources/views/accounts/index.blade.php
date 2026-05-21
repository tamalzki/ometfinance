<x-app-layout page-title="Accounts">
@php
    $entityColors = [
        'blue'    => ['dot'=>'bg-blue-500',    'tab'=>'border-blue-500 text-blue-600 bg-blue-50/40',    'badge'=>'bg-blue-50 text-blue-600 ring-1 ring-blue-200',    'accent'=>'border-l-blue-500',    'btn'=>'bg-blue-600 hover:bg-blue-700',    'ring'=>'ring-blue-500/30'],
        'violet'  => ['dot'=>'bg-violet-500',  'tab'=>'border-violet-500 text-violet-600 bg-violet-50/40','badge'=>'bg-violet-50 text-violet-600 ring-1 ring-violet-200','accent'=>'border-l-violet-500','btn'=>'bg-violet-600 hover:bg-violet-700','ring'=>'ring-violet-500/30'],
        'emerald' => ['dot'=>'bg-emerald-500', 'tab'=>'border-emerald-500 text-emerald-700 bg-emerald-50/40','badge'=>'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200','accent'=>'border-l-emerald-500','btn'=>'bg-emerald-600 hover:bg-emerald-700','ring'=>'ring-emerald-500/30'],
        'amber'   => ['dot'=>'bg-amber-500',   'tab'=>'border-amber-500 text-amber-700 bg-amber-50/40',  'badge'=>'bg-amber-50 text-amber-700 ring-1 ring-amber-200',  'accent'=>'border-l-amber-500',  'btn'=>'bg-amber-600 hover:bg-amber-700',  'ring'=>'ring-amber-500/30'],
        'teal'    => ['dot'=>'bg-teal-500',    'tab'=>'border-teal-500 text-teal-700 bg-teal-50/40',    'badge'=>'bg-teal-50 text-teal-700 ring-1 ring-teal-200',    'accent'=>'border-l-teal-500',    'btn'=>'bg-teal-600 hover:bg-teal-700',    'ring'=>'ring-teal-500/30'],
        'rose'    => ['dot'=>'bg-rose-500',    'tab'=>'border-rose-500 text-rose-600 bg-rose-50/40',    'badge'=>'bg-rose-50 text-rose-600 ring-1 ring-rose-200',    'accent'=>'border-l-rose-500',    'btn'=>'bg-rose-600 hover:bg-rose-700',    'ring'=>'ring-rose-500/30'],
    ];
    $color        = $entityColors[$entity->color] ?? $entityColors['blue'];
    $activeAccount = $account ?? null;

    $accountsForPicker = $allAccounts->map(fn ($a) => [
        'id' => $a->id,
        'label' => $a->entity->name . ' — ' . $a->name,
        'search' => strtolower($a->entity->name . ' ' . $a->name . ' ' . $a->bank_name . ' ' . (string) ($a->account_number ?? '')),
    ])->values();

    $pickerFirst = $allAccounts->first();
    $pickerSecond = $allAccounts->firstWhere('id', '!=', $pickerFirst?->id);
    $defaultTransferFromId = isset($activeAccount) ? $activeAccount->id : ($pickerFirst?->id);
    $defaultTransferToId = $pickerSecond?->id ?? $defaultTransferFromId;
@endphp

<div x-data="{
    showEntry: false,
    showTransfer: false,
    showNewAccount: false,
    showEditEntry: false,
    showEditAccount: false,
    entryType: 'out',
    entryDate: '{{ now()->toDateString() }}',

    /* Edit entry buffer */
    editId: null,
    editDate: '',
    editDescription: '',
    editType: 'out',
    editAmount: '',
    editNotes: '',
    editAction: '',

    openEdit(entry, action) {
        this.editId          = entry.id;
        this.editDate        = entry.date;
        this.editDescription = entry.description;
        this.editType        = entry.amount_out ? 'out' : 'in';
        this.editAmount      = entry.amount_out ?? entry.amount_in ?? '';
        this.editNotes       = entry.notes ?? '';
        this.editAction      = action;
        this.showEditEntry   = true;
    },

    search: '',
    accounts: {{ \Illuminate\Support\Js::from($accounts->map(fn ($a) => ['name' => $a->name, 'bank' => $a->bank_name])->values()) }},
    matches(name, bank) {
        const q = this.search.trim().toLowerCase();
        if (!q) return true;
        return (name || '').toLowerCase().includes(q)
            || (bank || '').toLowerCase().includes(q);
    },
    get visibleCount() {
        return this.accounts.filter(a => this.matches(a.name, a.bank)).length;
    },
}">

{{-- Flash --}}
@if (session('success'))
    <div class="mb-4 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
        <i data-lucide="check-circle-2" class="h-4 w-4 shrink-0 text-green-500"></i>
        {{ session('success') }}
    </div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
@endif

{{-- ── Tab bar (pill style, matches accounts.overall) ─────────────────────── --}}
<div class="mb-4 flex flex-wrap items-center gap-2">
    <a href="{{ route('accounts.overall') }}"
       class="rounded-full border border-gray-200 bg-white px-4 py-1.5 text-[13px] font-medium leading-none text-gray-600 transition hover:bg-gray-50 hover:border-gray-300">
        All entities
    </a>
    @foreach ($entities as $tab)
        <a href="{{ route('accounts.entity', $tab->slug) }}"
           class="rounded-full border px-4 py-1.5 text-[13px] font-medium leading-none transition
                  {{ $tab->id === $entity->id
                        ? 'bg-[#185FA5] text-white border-[#185FA5] shadow-sm'
                        : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:border-gray-300' }}">
            {{ $tab->name }}
        </a>
    @endforeach
    <button type="button" @click="showNewAccount = true"
            class="flex items-center gap-1.5 rounded-full border border-[#185FA5] bg-white px-4 py-1.5 text-[13px] font-medium leading-none text-[#185FA5] transition hover:bg-[#E6F1FB]">
        <i data-lucide="plus" class="h-3.5 w-3.5"></i>
        Add account
    </button>
    <a href="{{ route('accounts.transfers.index') }}"
       class="ml-auto flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 py-1.5 text-[13px] font-medium leading-none text-gray-600 transition hover:bg-gray-50 hover:border-gray-300">
        <i data-lucide="history" class="h-3.5 w-3.5"></i>
        Transfers history
    </a>
</div>

{{-- ── Two-panel layout ────────────────────────────────────────────────────── --}}
<div class="flex overflow-hidden rounded-2xl border-2 border-slate-200 bg-white shadow-sm"
     style="height: calc(100vh - 13.5rem);">

    {{-- Left panel: account list ──────────────────────────────────────────── --}}
    <div class="flex w-72 shrink-0 flex-col border-r-2 border-slate-200">

        {{-- Entity header --}}
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full {{ $color['dot'] }}"></span>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-500">{{ $entity->name }}</p>
                </div>
                @php $entityTotal = $accounts->sum(fn ($a) => $a->currentBalance()); @endphp
                <p class="text-[11px] font-semibold tabular-nums {{ $entityTotal < 0 ? 'text-red-600' : 'text-omet-navy' }}">
                    ₱{{ number_format($entityTotal, 0) }}
                </p>
            </div>
        </div>

        {{-- Search --}}
        <div class="border-b border-slate-200 bg-white px-3 py-2.5">
            <label class="relative block">
                <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400">
                    <i data-lucide="search" class="h-3.5 w-3.5"></i>
                </span>
                <input
                    type="text"
                    x-model="search"
                    placeholder="Search accounts…"
                    class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-7 text-[12px] text-gray-700 placeholder-gray-400 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10"
                >
                <button
                    type="button"
                    x-show="search.length > 0"
                    @click="search = ''"
                    style="display:none"
                    class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                    <i data-lucide="x" class="h-3 w-3"></i>
                </button>
            </label>
        </div>

        {{-- Account rows --}}
        <div class="flex-1 overflow-y-auto">
            @forelse ($accounts as $acc)
                @php
                    $bal      = $acc->currentBalance();
                    $isActive = $activeAccount && $activeAccount->id === $acc->id;
                @endphp
                <a href="{{ route('accounts.show', [$entity->slug, $acc->id]) }}"
                   x-show="matches(@js($acc->name), @js($acc->bank_name))"
                   data-entity="{{ $entity->name }}"
                   class="group flex items-center justify-between border-b border-slate-100 border-l-[3px] px-3.5 py-2.5 transition
                          {{ $isActive
                                ? 'bg-white ' . $color['accent'] . ' shadow-sm'
                                : 'border-l-transparent hover:bg-slate-50 hover:border-l-slate-200' }}">
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-semibold {{ $isActive ? 'text-omet-navy' : 'text-gray-700 group-hover:text-omet-navy' }}">
                            {{ $acc->name }}
                        </p>
                        <p class="mt-0.5 truncate text-[11px] text-gray-400">{{ $acc->bank_name }}</p>
                        @if ($acc->account_number)
                            <p class="mt-0.5 text-[10px] tracking-widest text-gray-300">
                                •••• {{ substr((string)$acc->account_number, -4) }}
                            </p>
                        @endif
                    </div>
                    <div class="ml-3 shrink-0 text-right">
                        <p class="text-[12px] font-semibold tabular-nums {{ $bal < 0 ? 'text-red-500' : ($isActive ? 'text-omet-navy' : 'text-gray-700') }}">
                            ₱{{ number_format($bal, 2) }}
                        </p>
                    </div>
                </a>
            @empty
                <div class="flex flex-col items-center justify-center px-4 py-14 text-center">
                    <i data-lucide="landmark" class="mb-2 h-8 w-8 text-gray-200"></i>
                    <p class="text-xs text-gray-400">No accounts yet.</p>
                </div>
            @endforelse

            {{-- Empty search state --}}
            <div x-show="search.length > 0 && visibleCount === 0"
                 style="display:none"
                 class="flex flex-col items-center justify-center px-4 py-10 text-center">
                <i data-lucide="search-x" class="mb-2 h-6 w-6 text-gray-200"></i>
                <p class="text-xs text-gray-400">No accounts match</p>
                <p class="mt-0.5 text-[11px] text-gray-300" x-text="'« ' + search + ' »'"></p>
            </div>
        </div>

        {{-- Add account button --}}
        <div class="border-t-2 border-slate-200 bg-slate-50 p-3">
            <button @click="showNewAccount = true"
                class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 py-2.5 text-xs font-semibold text-gray-500 transition hover:border-slate-400 hover:bg-white hover:text-gray-700">
                <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add Account
            </button>
        </div>
    </div>

    {{-- Right panel: ledger ────────────────────────────────────────────────── --}}
    <div class="flex flex-1 flex-col overflow-hidden">
        @isset($activeAccount)
            {{-- Account header --}}
            <div class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-[15px] font-semibold tracking-tight text-omet-navy">{{ $activeAccount->name }}</h3>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $color['badge'] }}">
                            {{ $entity->name }}
                        </span>
                        <button type="button"
                            @click="showEditAccount = true"
                            class="rounded p-1 text-gray-300 transition hover:bg-gray-100 hover:text-gray-600"
                            title="Edit account details / opening balance">
                            <i data-lucide="settings-2" class="h-3.5 w-3.5"></i>
                        </button>
                    </div>
                    <p class="mt-0.5 text-[11px] text-gray-400">
                        <span class="font-medium text-gray-500">{{ $activeAccount->bank_name }}</span>
                        @if ($activeAccount->account_number)
                            &nbsp;·&nbsp; {{ $activeAccount->account_number }}
                        @endif
                        &nbsp;·&nbsp; Opening
                        <span class="font-medium tabular-nums text-gray-500">
                            ₱{{ number_format($activeAccount->opening_balance, 2) }}
                        </span>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Current Balance</p>
                    @php $currentBal = $activeAccount->currentBalance(); @endphp
                    <p class="mt-0.5 text-lg font-semibold tracking-tight tabular-nums {{ $currentBal < 0 ? 'text-red-600' : 'text-omet-navy' }}">
                        ₱{{ number_format($currentBal, 2) }}
                    </p>
                </div>
            </div>

            {{-- Toolbar --}}
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50/70 px-5 py-2">
                <button @click="showEntry = true; entryType = 'out'; entryDate = '{{ now()->toDateString() }}'"
                    class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-[12px] font-semibold text-white shadow-sm transition {{ $color['btn'] }}">
                    <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add Entry
                </button>
                <button @click="showTransfer = true"
                    class="flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-semibold text-gray-600 transition hover:border-slate-300 hover:bg-gray-50">
                    <i data-lucide="arrow-left-right" class="h-3.5 w-3.5"></i> Transfer
                </button>

                {{-- Date range filter --}}
                <form method="GET" action="{{ route('accounts.show', [$entity->slug, $activeAccount->id]) }}"
                      class="ml-2 flex items-center gap-1.5 border-l border-slate-200 pl-3">
                    <i data-lucide="calendar-range" class="h-3.5 w-3.5 text-gray-400"></i>
                    <input type="date" name="from" value="{{ $from ?? '' }}"
                           class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[11px] text-gray-700 outline-none focus:border-omet-blue focus:ring-1 focus:ring-omet-blue/20">
                    <span class="text-[11px] text-gray-400">→</span>
                    <input type="date" name="to" value="{{ $to ?? '' }}"
                           class="h-7 rounded-md border border-slate-200 bg-white px-2 text-[11px] text-gray-700 outline-none focus:border-omet-blue focus:ring-1 focus:ring-omet-blue/20">
                    <button type="submit"
                            class="h-7 rounded-md bg-slate-700 px-2.5 text-[11px] font-semibold text-white transition hover:bg-slate-800">
                        Filter
                    </button>
                    @if (!empty($from) || !empty($to))
                        <a href="{{ route('accounts.show', [$entity->slug, $activeAccount->id]) }}"
                           class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                           title="Clear filter">
                            <i data-lucide="x" class="h-3.5 w-3.5"></i>
                        </a>
                    @endif
                </form>

                <div class="ml-auto flex items-center gap-1 text-[11px] font-medium text-gray-400">
                    <i data-lucide="rows-3" class="h-3 w-3"></i>
                    {{ $entries->count() }} {{ \Illuminate\Support\Str::plural('entry', $entries->count()) }}
                    @if (!empty($from) || !empty($to))
                        <span class="ml-1 rounded-full bg-blue-100 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">filtered</span>
                    @endif
                </div>
            </div>

            {{-- Ledger table --}}
            <div class="flex-1 overflow-y-auto">
                @if ($entries->isEmpty())
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <i data-lucide="file-spreadsheet" class="mb-3 h-12 w-12 text-gray-200"></i>
                        <p class="text-sm font-semibold text-gray-400">No entries yet</p>
                        <p class="mt-1 text-xs text-gray-300">Click "Add Entry" to record the first transaction.</p>
                    </div>
                @else
                    <table class="min-w-full border-separate border-spacing-0 text-[12px]">
                        <thead>
                            <tr class="bg-slate-50">
                                <th class="sticky top-0 z-10 border-b border-r border-slate-200 bg-slate-50 px-4 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500 w-[110px]">
                                    Date
                                </th>
                                <th class="sticky top-0 z-10 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                    Description
                                </th>
                                <th class="sticky top-0 z-10 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-red-500 w-[130px]">
                                    Money Out
                                </th>
                                <th class="sticky top-0 z-10 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-green-600 w-[130px]">
                                    Money In
                                </th>
                                <th class="sticky top-0 z-10 border-b border-r border-slate-200 bg-slate-50 px-4 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-gray-500 w-[130px]">
                                    Balance
                                </th>
                                <th class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-3 py-2 text-center text-[10px] font-semibold uppercase tracking-wider text-gray-400 w-[60px]">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $i => $entry)
                                <tr class="group transition hover:bg-blue-50/30 {{ $i % 2 === 0 ? 'bg-white' : 'bg-slate-50/40' }}">
                                    <td class="border-b border-r border-slate-100 px-4 py-2 text-[11px] tabular-nums text-gray-500 whitespace-nowrap">
                                        {{ $entry->date->format('M d, Y') }}
                                    </td>
                                    <td class="border-b border-r border-slate-100 px-3 py-2 text-gray-700">
                                        <div class="flex items-center gap-1.5">
                                            @if ($entry->isTransfer())
                                                <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-blue-100">
                                                    <i data-lucide="arrow-left-right" class="h-2.5 w-2.5 text-blue-500"></i>
                                                </span>
                                            @endif
                                            <span class="text-[12px] font-medium leading-snug">{{ $entry->description }}</span>
                                        </div>
                                        @if ($entry->notes)
                                            <p class="mt-0.5 text-[11px] text-gray-400">{{ $entry->notes }}</p>
                                        @endif
                                    </td>
                                    <td class="border-b border-r border-slate-100 px-3 py-2 text-right tabular-nums">
                                        @if ($entry->amount_out)
                                            <span class="text-[12px] font-semibold text-red-500">₱{{ number_format($entry->amount_out, 2) }}</span>
                                        @else
                                            <span class="text-gray-200">—</span>
                                        @endif
                                    </td>
                                    <td class="border-b border-r border-slate-100 px-3 py-2 text-right tabular-nums">
                                        @if ($entry->amount_in)
                                            <span class="text-[12px] font-semibold text-green-600">₱{{ number_format($entry->amount_in, 2) }}</span>
                                        @else
                                            <span class="text-gray-200">—</span>
                                        @endif
                                    </td>
                                    <td class="border-b border-r border-slate-100 px-4 py-2 text-right tabular-nums">
                                        <span class="text-[12px] font-semibold {{ $entry->running_balance < 0 ? 'text-red-600' : 'text-omet-navy' }}">
                                            ₱{{ number_format($entry->running_balance, 2) }}
                                        </span>
                                    </td>
                                    <td class="border-b border-slate-100 px-2 py-2 text-center">
                                        @if ($entry->isTransfer())
                                            <form method="POST"
                                                  action="{{ route('accounts.transfers.destroy', $entry->transfer_id) }}"
                                                  onsubmit="return confirm('Reverse this transfer? Both legs will be removed.');"
                                                  class="inline-flex">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="rounded p-1 text-gray-300 transition hover:bg-red-50 hover:text-red-600"
                                                        title="Reverse transfer (removes both legs)">
                                                    <i data-lucide="undo-2" class="h-3.5 w-3.5"></i>
                                                </button>
                                            </form>
                                        @else
                                            @php
                                                $editPayload = [
                                                    'id'          => $entry->id,
                                                    'date'        => $entry->date->toDateString(),
                                                    'description' => $entry->description,
                                                    'amount_out'  => $entry->amount_out,
                                                    'amount_in'   => $entry->amount_in,
                                                    'notes'       => $entry->notes,
                                                ];
                                                $editActionUrl = route('accounts.entries.update', $entry->id);
                                            @endphp
                                            <div class="inline-flex items-center gap-0.5">
                                                <button type="button"
                                                        @click="openEdit(@js($editPayload), @js($editActionUrl))"
                                                        class="rounded p-1 text-gray-300 transition hover:bg-slate-100 hover:text-slate-700"
                                                        title="Edit entry">
                                                    <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                                </button>
                                                <form method="POST"
                                                      action="{{ route('accounts.entries.destroy', $entry->id) }}"
                                                      onsubmit="return confirm('Delete this entry? This cannot be undone.');"
                                                      class="inline-flex">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="rounded p-1 text-gray-300 transition hover:bg-red-50 hover:text-red-600"
                                                            title="Delete entry">
                                                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50">
                                <td colspan="2" class="border-t border-r border-slate-200 px-4 py-2 text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                    @if (!empty($from) || !empty($to))
                                        Period totals
                                    @else
                                        Totals
                                    @endif
                                </td>
                                <td class="border-t border-r border-slate-200 px-3 py-2 text-right text-[12px] font-semibold tabular-nums text-red-500">
                                    ₱{{ number_format($entries->sum('amount_out'), 2) }}
                                </td>
                                <td class="border-t border-r border-slate-200 px-3 py-2 text-right text-[12px] font-semibold tabular-nums text-green-600">
                                    ₱{{ number_format($entries->sum('amount_in'), 2) }}
                                </td>
                                <td class="border-t border-r border-slate-200 px-4 py-2 text-right text-[12px] font-semibold tabular-nums text-omet-navy">
                                    ₱{{ number_format($activeAccount->currentBalance(), 2) }}
                                </td>
                                <td class="border-t border-slate-200"></td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>

        @else
            {{-- Empty state --}}
            <div class="flex flex-1 flex-col items-center justify-center text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-slate-200 bg-slate-50">
                    <i data-lucide="mouse-pointer-click" class="h-7 w-7 text-gray-300"></i>
                </div>
                <p class="text-base font-bold text-gray-400">Select an account</p>
                <p class="mt-1 text-sm text-gray-300">Pick an account from the left to view its ledger.</p>
            </div>
        @endisset
    </div>
</div>

{{-- ══ MODAL: Add Entry ══════════════════════════════════════════════════════ --}}
<div x-show="showEntry" style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
     x-transition:enter="transition duration-200 ease-out"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-150 ease-in"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @keydown.escape.window="showEntry = false">
    <div @click.outside="showEntry = false"
         class="w-full max-w-md overflow-hidden rounded-2xl border-2 border-slate-200 bg-white shadow-2xl"
         x-transition:enter="transition duration-200 ease-out"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">

        <div class="flex items-center justify-between border-b-2 border-slate-200 bg-slate-50 px-6 py-4">
            <div>
                <h4 class="text-base font-bold text-omet-navy">Add Ledger Entry</h4>
                @isset($activeAccount)
                    <p class="text-xs text-gray-400">{{ $activeAccount->name }} · {{ $activeAccount->bank_name }}</p>
                @endisset
            </div>
            <button @click="showEntry = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('accounts.entries.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            @isset($activeAccount)<input type="hidden" name="bank_account_id" value="{{ $activeAccount->id }}">@endisset

            {{-- Type toggle --}}
            <div>
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-500">Transaction Type</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="out" x-model="entryType" class="peer sr-only" checked>
                        <div class="flex items-center justify-center gap-1.5 rounded-xl border-2 py-3 text-sm font-bold transition
                                    peer-checked:border-red-400 peer-checked:bg-red-50 peer-checked:text-red-600
                                    border-slate-200 text-gray-400 hover:bg-gray-50">
                            <i data-lucide="arrow-up-right" class="h-4 w-4"></i> Money Out
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="in" x-model="entryType" class="peer sr-only">
                        <div class="flex items-center justify-center gap-1.5 rounded-xl border-2 py-3 text-sm font-bold transition
                                    peer-checked:border-green-400 peer-checked:bg-green-50 peer-checked:text-green-600
                                    border-slate-200 text-gray-400 hover:bg-gray-50">
                            <i data-lucide="arrow-down-left" class="h-4 w-4"></i> Money In
                        </div>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Date</label>
                    <input type="date" name="date" :value="entryDate" required
                        class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Amount (₱)</label>
                    <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required
                        class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Description</label>
                <input type="text" name="description" placeholder="e.g. BGC Payroll, APMC Collection…" required maxlength="255"
                    class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">
                    Notes <span class="font-normal normal-case text-gray-400">(optional)</span>
                </label>
                <textarea name="notes" rows="2" placeholder="Reference number, remarks…" maxlength="500"
                    class="w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10"></textarea>
            </div>

            <div class="flex gap-3 border-t border-slate-100 pt-4">
                <button type="button" @click="showEntry = false"
                    class="flex-1 rounded-xl border-2 border-slate-200 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 rounded-xl py-2.5 text-sm font-bold text-white shadow-sm transition {{ $color['btn'] }}">
                    Save Entry
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══ MODAL: Transfer ══════════════════════════════════════════════════════ --}}
<div x-show="showTransfer" style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
     x-transition:enter="transition duration-200 ease-out"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-150 ease-in"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @keydown.escape.window="showTransfer = false">
    <div @click.outside="showTransfer = false"
         class="w-full max-w-md overflow-visible rounded-2xl border-2 border-slate-200 bg-white shadow-2xl"
         x-transition:enter="transition duration-200 ease-out"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">

        <div class="flex items-center justify-between border-b-2 border-slate-200 bg-slate-50 px-6 py-4">
            <div>
                <h4 class="text-base font-bold text-omet-navy">Transfer Between Accounts</h4>
                <p class="text-xs text-gray-400">Both accounts will be updated automatically.</p>
            </div>
            <button @click="showTransfer = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('accounts.transfers.store') }}" class="overflow-visible px-6 py-5 space-y-4">
            @csrf
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">From Account</label>
                @include('accounts.partials.account-combobox', [
                    'fieldName' => 'from_account_id',
                    'accountsForPicker' => $accountsForPicker,
                    'defaultId' => $defaultTransferFromId,
                    'buttonClass' => 'flex h-11 w-full items-center justify-between gap-2 rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-left text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10',
                ])
            </div>

            <div class="flex items-center gap-3">
                <div class="h-px flex-1 bg-slate-200"></div>
                <div class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-slate-200 bg-white">
                    <i data-lucide="arrow-down" class="h-3.5 w-3.5 text-gray-400"></i>
                </div>
                <div class="h-px flex-1 bg-slate-200"></div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">To Account</label>
                @include('accounts.partials.account-combobox', [
                    'fieldName' => 'to_account_id',
                    'accountsForPicker' => $accountsForPicker,
                    'defaultId' => $defaultTransferToId,
                    'buttonClass' => 'flex h-11 w-full items-center justify-between gap-2 rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-left text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10',
                ])
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Date</label>
                    <input type="date" name="date" value="{{ now()->toDateString() }}" required
                        class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Amount (₱)</label>
                    <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required
                        class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">
                    Memo <span class="font-normal normal-case text-gray-400">(optional)</span>
                </label>
                <input type="text" name="memo" placeholder="Reason for transfer…" maxlength="255"
                    class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
            </div>

            <div class="flex gap-3 border-t border-slate-100 pt-4">
                <button type="button" @click="showTransfer = false"
                    class="flex-1 rounded-xl border-2 border-slate-200 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 rounded-xl bg-omet-blue py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-omet-lightblue">
                    Confirm Transfer
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══ MODAL: Add Bank Account ═══════════════════════════════════════════════ --}}
<div x-show="showNewAccount" style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
     x-transition:enter="transition duration-200 ease-out"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-150 ease-in"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @keydown.escape.window="showNewAccount = false">
    <div @click.outside="showNewAccount = false"
         class="w-full max-w-md overflow-hidden rounded-2xl border-2 border-slate-200 bg-white shadow-2xl"
         x-transition:enter="transition duration-200 ease-out"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">

        <div class="flex items-center justify-between border-b-2 border-slate-200 bg-slate-50 px-6 py-4">
            <div>
                <h4 class="text-base font-bold text-omet-navy">Add Bank Account</h4>
                <p class="text-xs text-gray-400">
                    Business category defaults to <span class="font-semibold text-gray-600">{{ $entity->name }}</span> — change below if needed.
                </p>
            </div>
            <button @click="showNewAccount = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('accounts.bank-accounts.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Business category</label>
                <select name="entity_id" required
                    class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
                    @foreach ($entities as $ent)
                        <option value="{{ $ent->id }}" {{ $ent->id === $entity->id ? 'selected' : '' }}>{{ $ent->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-gray-400">Onemark, Corange, Personal MRJ, Joint, Dollar, Kids, etc.</p>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Account Name</label>
                <input type="text" name="name" placeholder="e.g. BDO Savings" required maxlength="255"
                    class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Bank Name</label>
                <input type="text" name="bank_name" placeholder="e.g. BDO Unibank" required maxlength="255"
                    class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">
                        Account Number <span class="font-normal normal-case text-gray-400">(optional)</span>
                    </label>
                    <input type="text" name="account_number" placeholder="e.g. 1234-5678-9012" maxlength="100"
                        class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">
                        Opening Balance (₱)
                    </label>
                    <input type="number" name="opening_balance" value="0" step="0.01"
                        class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
                </div>
            </div>
            <div class="flex gap-3 border-t border-slate-100 pt-4">
                <button type="button" @click="showNewAccount = false"
                    class="flex-1 rounded-xl border-2 border-slate-200 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 rounded-xl py-2.5 text-sm font-bold text-white shadow-sm transition {{ $color['btn'] }}">
                    Add Account
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══ MODAL: Edit Entry ═════════════════════════════════════════════════════ --}}
<div x-show="showEditEntry" style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
     x-transition:enter="transition duration-200 ease-out"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @keydown.escape.window="showEditEntry = false">
    <div @click.outside="showEditEntry = false"
         class="w-full max-w-md overflow-hidden rounded-2xl border-2 border-slate-200 bg-white shadow-2xl">

        <div class="flex items-center justify-between border-b-2 border-slate-200 bg-slate-50 px-6 py-4">
            <div>
                <h4 class="text-base font-bold text-omet-navy">Edit Ledger Entry</h4>
                @isset($activeAccount)
                    <p class="text-xs text-gray-400">{{ $activeAccount->name }} · {{ $activeAccount->bank_name }}</p>
                @endisset
            </div>
            <button type="button" @click="showEditEntry = false"
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <form :action="editAction" method="POST" class="px-6 py-5 space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-500">Transaction Type</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="out" x-model="editType" class="peer sr-only">
                        <div class="flex items-center justify-center gap-1.5 rounded-xl border-2 py-3 text-sm font-bold transition
                                    peer-checked:border-red-400 peer-checked:bg-red-50 peer-checked:text-red-600
                                    border-slate-200 text-gray-400 hover:bg-gray-50">
                            <i data-lucide="arrow-up-right" class="h-4 w-4"></i> Money Out
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="in" x-model="editType" class="peer sr-only">
                        <div class="flex items-center justify-center gap-1.5 rounded-xl border-2 py-3 text-sm font-bold transition
                                    peer-checked:border-green-400 peer-checked:bg-green-50 peer-checked:text-green-600
                                    border-slate-200 text-gray-400 hover:bg-gray-50">
                            <i data-lucide="arrow-down-left" class="h-4 w-4"></i> Money In
                        </div>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Date</label>
                    <input type="date" name="date" x-model="editDate" required
                        class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Amount (₱)</label>
                    <input type="number" name="amount" min="0.01" step="0.01" x-model="editAmount" required
                        class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Description</label>
                <input type="text" name="description" x-model="editDescription" required maxlength="255"
                    class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">
                    Notes <span class="font-normal normal-case text-gray-400">(optional)</span>
                </label>
                <textarea name="notes" rows="2" x-model="editNotes" maxlength="500"
                    class="w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10"></textarea>
            </div>

            <div class="flex gap-3 border-t border-slate-100 pt-4">
                <button type="button" @click="showEditEntry = false"
                    class="flex-1 rounded-xl border-2 border-slate-200 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 rounded-xl py-2.5 text-sm font-bold text-white shadow-sm transition {{ $color['btn'] }}">
                    Save changes
                </button>
            </div>
        </form>
    </div>
</div>

@isset($activeAccount)
{{-- ══ MODAL: Edit Account ═══════════════════════════════════════════════════ --}}
<div x-show="showEditAccount" style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
     x-transition:enter="transition duration-200 ease-out"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @keydown.escape.window="showEditAccount = false">
    <div @click.outside="showEditAccount = false"
         class="w-full max-w-md overflow-hidden rounded-2xl border-2 border-slate-200 bg-white shadow-2xl">

        <div class="flex items-center justify-between border-b-2 border-slate-200 bg-slate-50 px-6 py-4">
            <div>
                <h4 class="text-base font-bold text-omet-navy">Edit Account</h4>
                <p class="text-xs text-gray-400">{{ $activeAccount->name }} · {{ $entity->name }}</p>
            </div>
            <button type="button" @click="showEditAccount = false"
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('accounts.bank-accounts.update', $activeAccount->id) }}"
              class="px-6 py-5 space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Account Name</label>
                <input type="text" name="name" required maxlength="255"
                    value="{{ old('name', $activeAccount->name) }}"
                    class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Bank Name</label>
                <input type="text" name="bank_name" required maxlength="255"
                    value="{{ old('bank_name', $activeAccount->bank_name) }}"
                    class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">
                        Account # <span class="font-normal normal-case text-gray-400">(optional)</span>
                    </label>
                    <input type="text" name="account_number" maxlength="100"
                        value="{{ old('account_number', $activeAccount->account_number) }}"
                        class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Opening Balance (₱)</label>
                    <input type="number" name="opening_balance" step="0.01"
                        value="{{ old('opening_balance', $activeAccount->opening_balance) }}"
                        class="h-11 w-full rounded-xl border-2 border-slate-200 bg-gray-50 px-3 text-sm font-medium text-gray-800 outline-none transition focus:border-omet-blue focus:bg-white focus:ring-2 focus:ring-omet-blue/10">
                </div>
            </div>

            <p class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-[11px] text-amber-700">
                <i data-lucide="info" class="mr-1 inline h-3 w-3"></i>
                Changing the opening balance recomputes every running balance for this account.
            </p>

            <div class="flex gap-3 border-t border-slate-100 pt-4">
                <button type="button" @click="showEditAccount = false"
                    class="flex-1 rounded-xl border-2 border-slate-200 py-2.5 text-sm font-bold text-gray-600 transition hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 rounded-xl py-2.5 text-sm font-bold text-white shadow-sm transition {{ $color['btn'] }}">
                    Save account
                </button>
            </div>
        </form>
    </div>
</div>
@endisset

</div>{{-- end x-data --}}
</x-app-layout>
