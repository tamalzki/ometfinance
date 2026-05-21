<x-app-layout page-title="Reports">
@php
    $fmt = fn ($n) => '₱' . number_format((float) $n, 2);
    $tabs = [
        'overall'          => ['label' => 'Overall Position', 'icon' => 'gauge',         'route' => 'reports'],
        'cash-outflow'     => ['label' => 'Cash Outflow',     'icon' => 'arrow-up-circle','route' => 'reports.cashOutflow'],
        'account-balances' => ['label' => 'Account Balances', 'icon' => 'landmark',      'route' => 'reports.accountBalances'],
        'transfers'        => ['label' => 'Transfers',        'icon' => 'arrow-left-right','route' => 'reports.transfers'],
        'collections'      => ['label' => 'Collections',      'icon' => 'arrow-down-circle','route' => 'reports.collections'],
    ];
    $hasFilters = ! in_array($activeTab, ['overall'], true);
    $reportRouteUrl = route($tabs[$activeTab]['route']);
@endphp

<style media="print">
    /* Print: strip nav/sidebar/toolbar, show only the report */
    body { background: #fff !important; color: #000 !important; }
    aside, header, .no-print { display: none !important; }
    .lg\:ml-64 { margin-left: 0 !important; }
    main { padding: 0 !important; }
    .reports-print-only { display: block !important; }
    table { page-break-inside: auto; }
    tr    { page-break-inside: avoid; page-break-after: auto; }
    .page-break { page-break-before: always; }
</style>

<div class="space-y-4">

    {{-- Print-only header --}}
    <div class="reports-print-only hidden">
        <h1 class="text-xl font-bold">OMET Finance System</h1>
        <p class="text-sm">{{ $tabs[$activeTab]['label'] }} · printed {{ now()->format('M j, Y g:i A') }}</p>
        <hr class="my-3 border-black">
    </div>

    {{-- Header --}}
    <div class="no-print flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-omet-navy">Reports</h1>
            <p class="text-xs text-slate-500">{{ $tabs[$activeTab]['label'] }}</p>
        </div>
    </div>

    {{-- Tab nav --}}
    <nav class="no-print flex shrink-0 overflow-x-auto border-b border-gray-200">
        @foreach ($tabs as $key => $t)
            @php $active = $activeTab === $key; @endphp
            <a href="{{ route($t['route']) }}"
                @class([
                    '-mb-px flex items-center gap-2 whitespace-nowrap px-4 py-2.5 text-sm transition-colors duration-150',
                    'border-b-2 border-omet-blue text-omet-blue font-semibold bg-blue-50/40' => $active,
                    'border-b-2 border-transparent text-gray-500 hover:text-omet-navy hover:border-gray-300' => ! $active,
                ])>
                <i data-lucide="{{ $t['icon'] }}" class="h-4 w-4"></i>
                {{ $t['label'] }}
            </a>
        @endforeach
    </nav>

    {{-- Filter bar (skip for Overall Position) --}}
    @if ($hasFilters)
        <form method="GET" action="{{ $reportRouteUrl }}"
              class="no-print flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2.5">
            <div>
                <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">From</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                       class="mt-1 h-9 min-w-[8.5rem] rounded-md border border-slate-200 bg-white px-3 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
            </div>
            <div>
                <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">To</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                       class="mt-1 h-9 min-w-[8.5rem] rounded-md border border-slate-200 bg-white px-3 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
            </div>

            @if (in_array($activeTab, ['cash-outflow', 'collections'], true))
                <div>
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Project</label>
                    <select name="project_id"
                            class="mt-1 h-9 min-w-[12rem] rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                        <option value="">All projects</option>
                        @foreach ($projectsForFilter as $p)
                            <option value="{{ $p->id }}" {{ (string) $filters['project_id'] === (string) $p->id ? 'selected' : '' }}>
                                {{ $p->name }}{{ $p->kind === 'in_house' ? ' (in-house)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if (in_array($activeTab, ['cash-outflow', 'account-balances', 'transfers'], true))
                <div>
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Entity</label>
                    <select name="entity"
                            class="mt-1 h-9 min-w-[10rem] rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                        <option value="">All entities</option>
                        @foreach ($entities as $e)
                            <option value="{{ $e->slug }}" {{ $filters['entity'] === $e->slug ? 'selected' : '' }}>{{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($activeTab === 'transfers')
                <div>
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Account</label>
                    <select name="account_id"
                            class="mt-1 h-9 min-w-[14rem] rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                        <option value="">All accounts</option>
                        @foreach ($accountsForFilter as $a)
                            <option value="{{ $a->id }}" {{ (string) $filters['account_id'] === (string) $a->id ? 'selected' : '' }}>
                                {{ $a->entity?->name }} — {{ $a->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="flex gap-2">
                <button type="submit"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md bg-omet-blue px-3.5 text-[12.5px] font-semibold text-white shadow-sm hover:bg-omet-lightblue">
                    <i data-lucide="play" class="h-3.5 w-3.5"></i>
                    Generate
                </button>
                <a href="{{ $reportRouteUrl }}"
                   class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-[12.5px] font-medium text-slate-600 hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </form>
    @endif

    {{-- Status line + export buttons --}}
    <div class="no-print flex flex-wrap items-center justify-between gap-2">
        <p class="text-[11.5px] text-slate-500">
            @switch($activeTab)
                @case('cash-outflow')
                    {{ $cashOutflow['row_count'] ?? 0 }} expense rows · {{ count($cashOutflow['groups'] ?? []) }} projects
                    @break
                @case('account-balances')
                    {{ $accountBalances['row_count'] ?? 0 }} accounts across {{ count($accountBalances['groups'] ?? []) }} entities
                    @break
                @case('transfers')
                    {{ $transfers['row_count'] ?? 0 }} transfers
                    @break
                @case('collections')
                    {{ $collections['row_count'] ?? 0 }} collection entries across {{ count($collections['groups'] ?? []) }} projects
                    @break
                @default
                    Snapshot as of {{ $overall['generated_at']->format('M j, Y g:i A') }}
            @endswitch
            · {{ $hasFilters ? (($filters['date_from'] || $filters['date_to']) ? trim(($filters['date_from'] ?: 'beginning') . ' → ' . ($filters['date_to'] ?: 'today')) : 'all dates') : 'live figures' }}
        </p>
        <div class="flex flex-wrap items-center gap-1.5">
            <form method="POST" action="{{ route('reports.exportPdf') }}" class="inline-flex">
                @csrf
                <input type="hidden" name="report" value="{{ $activeTab }}">
                <input type="hidden" name="date_from" value="{{ $filters['date_from'] }}">
                <input type="hidden" name="date_to"   value="{{ $filters['date_to'] }}">
                <input type="hidden" name="project_id" value="{{ $filters['project_id'] }}">
                <input type="hidden" name="entity"     value="{{ $filters['entity'] }}">
                <input type="hidden" name="account_id" value="{{ $filters['account_id'] }}">
                <button type="submit"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-[12px] font-medium text-slate-600 hover:bg-slate-50">
                    <i data-lucide="file-down" class="h-3.5 w-3.5"></i>
                    PDF
                </button>
            </form>
            <form method="POST" action="{{ route('reports.exportExcel') }}" class="inline-flex">
                @csrf
                <input type="hidden" name="report" value="{{ $activeTab }}">
                <input type="hidden" name="date_from" value="{{ $filters['date_from'] }}">
                <input type="hidden" name="date_to"   value="{{ $filters['date_to'] }}">
                <input type="hidden" name="project_id" value="{{ $filters['project_id'] }}">
                <input type="hidden" name="entity"     value="{{ $filters['entity'] }}">
                <input type="hidden" name="account_id" value="{{ $filters['account_id'] }}">
                <button type="submit"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-[12px] font-medium text-slate-600 hover:bg-slate-50">
                    <i data-lucide="file-spreadsheet" class="h-3.5 w-3.5"></i>
                    Excel
                </button>
            </form>
            <button type="button" onclick="window.print()"
                    class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-[12px] font-medium text-slate-600 hover:bg-slate-50">
                <i data-lucide="printer" class="h-3.5 w-3.5"></i>
                Print
            </button>
        </div>
    </div>

    {{-- ── Report body ────────────────────────────────────────────────── --}}
    @switch($activeTab)

        {{-- OVERALL POSITION --}}
        @case('overall')
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                @foreach ([
                    ['Cash in bank',         $overall['cash_in_bank'],     'piggy-bank',      'text-omet-navy'],
                    ['Project expenses',     $overall['project_expenses'], 'arrow-up-circle', 'text-red-600'],
                    ['Collections received', $overall['collections'],      'arrow-down-circle','text-emerald-700'],
                    ['Transfers moved',      $overall['transfers_made'],   'arrow-left-right','text-slate-700'],
                    ['Net cash position',    $overall['net_position'],     'wallet',          'text-omet-navy'],
                ] as [$label, $val, $icon, $color])
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
                            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-slate-50">
                                <i data-lucide="{{ $icon }}" class="h-3.5 w-3.5 text-slate-500"></i>
                            </span>
                        </div>
                        <p class="mt-2 text-xl font-bold tabular-nums {{ $color }}">{{ $fmt($val) }}</p>
                    </div>
                @endforeach
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 text-[12.5px] text-slate-600">
                <p class="font-semibold text-omet-navy">Snapshot</p>
                <ul class="mt-1.5 space-y-0.5 text-slate-500">
                    <li>{{ $overall['accounts_count'] }} bank account{{ $overall['accounts_count'] === 1 ? '' : 's' }} across {{ count($entities) }} entities</li>
                    <li>{{ $overall['projects_count'] }} project{{ $overall['projects_count'] === 1 ? '' : 's' }} tracked</li>
                    <li>{{ $overall['transfers_count'] }} inter-account transfer{{ $overall['transfers_count'] === 1 ? '' : 's' }} on record</li>
                    <li>Generated {{ $overall['generated_at']->format('M j, Y · g:i A') }}</li>
                </ul>
            </div>
            @break

        {{-- CASH OUTFLOW --}}
        @case('cash-outflow')
            @if ($cashOutflow['groups']->isEmpty())
                <div class="rounded-lg border border-dashed border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                    No data found for selected period.
                </div>
            @else
                <div class="data-grid overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-slate-50">
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</th>
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Description</th>
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Category</th>
                                <th class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($cashOutflow['groups'] as $g)
                            <tr class="bg-slate-100/80 page-break">
                                <td colspan="4" class="text-left text-[11.5px] font-bold uppercase tracking-wider text-omet-navy">
                                    {{ $g->project->name }}{{ $g->project->kind === 'in_house' ? ' (in-house)' : '' }}
                                </td>
                            </tr>
                            @foreach ($g->items as $e)
                                <tr class="hover:bg-slate-50/70 {{ $loop->even ? 'bg-slate-50/30' : '' }}">
                                    <td class="tabular-nums text-slate-600 whitespace-nowrap">{{ $e->spent_on->format('M j, Y') }}</td>
                                    <td class="text-slate-700">{{ $e->description ?: '—' }}</td>
                                    <td class="capitalize text-slate-500">{{ $e->category ?: '—' }}</td>
                                    <td class="text-right font-semibold tabular-nums text-red-600">{{ $fmt($e->amount) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-amber-50/60 font-bold">
                                <td colspan="3" class="text-right text-[11px] uppercase tracking-wide text-amber-900">Subtotal — {{ $g->project->name }}</td>
                                <td class="text-right tabular-nums text-amber-900">{{ $fmt($g->subtotal) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right text-[11px] font-bold uppercase tracking-wide text-omet-navy">Grand Total</td>
                                <td class="text-right text-[13px] font-bold tabular-nums text-red-600">{{ $fmt($cashOutflow['grand_total']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
            @break

        {{-- ACCOUNT BALANCES --}}
        @case('account-balances')
            @if (($accountBalances['row_count'] ?? 0) === 0)
                <div class="rounded-lg border border-dashed border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                    No accounts found.
                </div>
            @else
                <div class="data-grid overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-slate-50">
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Entity</th>
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Bank</th>
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Account Name</th>
                                <th class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Current Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($accountBalances['groups'] as $g)
                            @if ($g->rows->isEmpty()) @continue @endif
                            <tr class="bg-slate-100/80">
                                <td colspan="4" class="text-left text-[11.5px] font-bold uppercase tracking-wider text-omet-navy">{{ $g->entity->name }}</td>
                            </tr>
                            @foreach ($g->rows as $r)
                                <tr class="hover:bg-slate-50/70 {{ $loop->even ? 'bg-slate-50/30' : '' }}">
                                    <td class="text-slate-500">{{ $g->entity->name }}</td>
                                    <td class="uppercase text-slate-700">{{ $r->account->bank_name }}</td>
                                    <td class="text-slate-700">{{ $r->account->name }}</td>
                                    <td class="text-right font-semibold tabular-nums {{ $r->balance < 0 ? 'text-red-600' : 'text-omet-navy' }}">{{ $fmt($r->balance) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-amber-50/60 font-bold">
                                <td colspan="3" class="text-right text-[11px] uppercase tracking-wide text-amber-900">Subtotal — {{ $g->entity->name }}</td>
                                <td class="text-right tabular-nums text-amber-900">{{ $fmt($g->subtotal) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right text-[11px] font-bold uppercase tracking-wide text-omet-navy">Total Cash in Bank</td>
                                <td class="text-right text-[13px] font-bold tabular-nums text-emerald-700">{{ $fmt($accountBalances['grand_total']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
            @break

        {{-- TRANSFERS --}}
        @case('transfers')
            @if ($transfers['rows']->isEmpty())
                <div class="rounded-lg border border-dashed border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                    No transfers found for selected period.
                </div>
            @else
                <div class="data-grid overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-slate-50">
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</th>
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">From</th>
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">To</th>
                                <th class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Memo</th>
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Recorded</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transfers['rows'] as $t)
                                <tr class="hover:bg-slate-50/70 {{ $loop->even ? 'bg-slate-50/30' : '' }}">
                                    <td class="tabular-nums text-slate-600 whitespace-nowrap">{{ $t->date->format('M j, Y') }}</td>
                                    <td class="text-slate-700">{{ $t->fromAccount?->name ?? '—' }}@if ($t->fromAccount?->entity)<span class="text-[10px] text-slate-400"> · {{ $t->fromAccount->entity->name }}</span>@endif</td>
                                    <td class="text-slate-700">{{ $t->toAccount?->name ?? '—' }}@if ($t->toAccount?->entity)<span class="text-[10px] text-slate-400"> · {{ $t->toAccount->entity->name }}</span>@endif</td>
                                    <td class="text-right font-semibold tabular-nums text-omet-navy">{{ $fmt($t->amount) }}</td>
                                    <td class="text-slate-500">{{ $t->memo ?? '—' }}</td>
                                    <td class="tabular-nums text-slate-400">{{ optional($t->created_at)->format('M j, Y g:i A') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @if ($filters['entity'])
                                <tr>
                                    <td colspan="3" class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-600">Transfers out of selected entity</td>
                                    <td class="text-right tabular-nums text-red-600">{{ $fmt($transfers['entity_out']) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-600">Transfers into selected entity</td>
                                    <td class="text-right tabular-nums text-emerald-700">{{ $fmt($transfers['entity_in']) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            @endif
                            <tr>
                                <td colspan="3" class="text-right text-[11px] font-bold uppercase tracking-wide text-omet-navy">Grand Total</td>
                                <td class="text-right text-[13px] font-bold tabular-nums text-omet-navy">{{ $fmt($transfers['grand_total']) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
            @break

        {{-- COLLECTIONS & ALLOCATION --}}
        @case('collections')
            @if ($collections['groups']->isEmpty())
                <div class="rounded-lg border border-dashed border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                    No collections found for selected period.
                </div>
            @else
                <div class="data-grid overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-slate-50">
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Project</th>
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Collection Date</th>
                                <th class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total Collected</th>
                                <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Allocation Category</th>
                                <th class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">%</th>
                                <th class="text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($collections['groups'] as $g)
                            <tr class="bg-slate-100/80 page-break">
                                <td colspan="6" class="text-left text-[11.5px] font-bold uppercase tracking-wider text-omet-navy">{{ $g->project->name }}</td>
                            </tr>
                            @foreach ($g->rows as $r)
                                @php $kpi = $r->category_kind === \App\Models\ProjectAllocationLine::KIND_KPI; @endphp
                                <tr class="{{ $kpi ? 'bg-amber-50/30' : ($loop->even ? 'bg-slate-50/30' : '') }} hover:bg-slate-50/70">
                                    <td class="text-slate-500">{{ $g->project->name }}</td>
                                    <td class="tabular-nums text-slate-600 whitespace-nowrap">{{ $r->collection_date->format('M j, Y') }}</td>
                                    <td class="text-right tabular-nums text-slate-700">{{ $fmt($r->collection_total) }}</td>
                                    <td class="text-slate-700 {{ $kpi ? 'font-semibold text-amber-900' : '' }}">{{ $r->category }}</td>
                                    <td class="text-right tabular-nums text-slate-500">{{ number_format($r->percent * 100, 2) }}%</td>
                                    <td class="text-right font-semibold tabular-nums {{ $kpi ? 'text-amber-700' : 'text-slate-700' }}">{{ $fmt($r->amount) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-amber-50/60 font-bold">
                                <td colspan="2" class="text-right text-[11px] uppercase tracking-wide text-amber-900">Subtotal — {{ $g->project->name }} (collected)</td>
                                <td class="text-right tabular-nums text-amber-900">{{ $fmt($g->subtotal) }}</td>
                                <td colspan="3"></td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-right text-[11px] font-bold uppercase tracking-wide text-omet-navy">Grand Total Collected</td>
                                <td class="text-right text-[13px] font-bold tabular-nums text-emerald-700">{{ $fmt($collections['grand_total']) }}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
            @break

    @endswitch
</div>
</x-app-layout>
