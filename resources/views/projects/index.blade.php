<x-app-layout :page-title="$kindLabel ?? 'Projects'">
@php
    $isExternal = ($kind ?? 'external') === 'external';

    $statusLabels = [
        'planning'    => 'Planning',
        'active'      => 'Active',
        'in_progress' => 'In progress',
        'on-hold'     => 'On hold',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
    ];
    $badgeClasses = [
        'planning'    => 'bg-slate-100 text-slate-700',
        'active'      => 'bg-blue-100 text-blue-800',
        'in_progress' => 'bg-indigo-100 text-indigo-800',
        'on-hold'     => 'bg-amber-100 text-amber-900',
        'completed'   => 'bg-green-100 text-green-800',
        'cancelled'   => 'bg-red-100 text-red-800',
    ];

    $fmt = fn ($n) => '₱' . number_format((float) $n, 2);
    $today = \Illuminate\Support\Carbon::today();
@endphp

<div
    x-data="{
        showAdd: {{ $errors->any() ? 'true' : 'false' }},
        kind: '{{ old('kind', $kind ?? 'external') }}',
        search: '',
        statusFilter: 'all',
        addPreview: null,
        showImageModal: false,
        imageProjectId: null,
        imageProjectName: '',
        imagePreview: null,
        openAddModal() {
            this.kind = '{{ $kind ?? 'external' }}';
            this.addPreview = null;
            this.showAdd = true;
        },
        previewFile(event, target) {
            const file = event.target.files?.[0];
            if (!file) { this[target] = null; return; }
            const reader = new FileReader();
            reader.onload = (e) => this[target] = e.target.result;
            reader.readAsDataURL(file);
        },
        openImageUpload(id, name) {
            this.imageProjectId = id;
            this.imageProjectName = name;
            this.imagePreview = null;
            this.showImageModal = true;
        }
    }"
    @open-image-upload.window="openImageUpload($event.detail.id, $event.detail.name)"
    class="space-y-6"
>

    {{-- ── Page header ────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-omet-navy">{{ $kindLabel ?? 'Projects' }}</h1>
            <p class="text-xs text-gray-500">
                {{ $summary['active_count'] }} active · {{ $summary['total_count'] }} total
                @if ($summary['completed_count'] > 0) · {{ $summary['completed_count'] }} completed @endif
            </p>
        </div>
        <button
            @click="openAddModal()"
            class="inline-flex items-center gap-1.5 rounded-lg bg-omet-blue px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-omet-lightblue"
        >
            <i data-lucide="plus" class="h-4 w-4"></i>
            New {{ $isExternal ? 'project' : 'in-house project' }}
        </button>
    </div>

    {{-- ── Financial summary cards ────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">

        @if ($isExternal)
            {{-- Contract value --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Contract value</p>
                    <div class="shrink-0 w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
                        <i data-lucide="file-signature" class="h-4 w-4"></i>
                    </div>
                </div>
                <p class="text-xl font-medium text-blue-600 mb-1 tabular-nums truncate" title="{{ $fmt($summary['contract_value']) }}">{{ $fmt($summary['contract_value']) }}</p>
                <p class="text-xs text-gray-400">across {{ $summary['active_count'] }} active project{{ $summary['active_count'] === 1 ? '' : 's' }}</p>
            </div>

            {{-- Collected --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Total collected</p>
                    <div class="shrink-0 w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center text-green-600">
                        <i data-lucide="arrow-down-circle" class="h-4 w-4"></i>
                    </div>
                </div>
                <p class="text-xl font-medium text-green-600 mb-1 tabular-nums truncate" title="{{ $fmt($summary['total_collected']) }}">{{ $fmt($summary['total_collected']) }}</p>
                <p class="text-xs text-gray-400">{{ $summary['collection_pct'] }}% of contract</p>
            </div>

            {{-- Outflow --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Outflow</p>
                    <div class="shrink-0 w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center text-red-600">
                        <i data-lucide="arrow-up-circle" class="h-4 w-4"></i>
                    </div>
                </div>
                <p class="text-xl font-medium text-red-600 mb-1 tabular-nums truncate" title="{{ $fmt($summary['total_outflow']) }}">{{ $fmt($summary['total_outflow']) }}</p>
                <p class="text-xs text-gray-400">costs, vendors, payroll</p>
            </div>

            {{-- Net cash --}}
            @php $netPositive = $summary['net_cash'] >= 0; @endphp
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Net cash</p>
                    <div class="shrink-0 w-8 h-8 rounded-lg {{ $netPositive ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' }} flex items-center justify-center">
                        <i data-lucide="wallet" class="h-4 w-4"></i>
                    </div>
                </div>
                <p class="text-xl font-medium mb-1 tabular-nums truncate {{ $netPositive ? 'text-green-600' : 'text-red-600' }}" title="{{ $fmt($summary['net_cash']) }}">{{ $fmt($summary['net_cash']) }}</p>
                <p class="text-xs text-gray-400">inflow − outflow</p>
            </div>
        @else
            @php
                $budgetUsedPct = $summary['contract_value'] > 0
                    ? min(100, round($summary['total_outflow'] / $summary['contract_value'] * 100, 1))
                    : 0;
                $remaining = max(0, $summary['contract_value'] - $summary['total_outflow']);
                $positive = $summary['contract_value'] > 0 ? $remaining > 0 : $summary['net_cash'] >= 0;
            @endphp

            {{-- Total budget --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Total budget</p>
                    <div class="shrink-0 w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                        <i data-lucide="piggy-bank" class="h-4 w-4"></i>
                    </div>
                </div>
                <p class="text-xl font-medium text-indigo-600 mb-1 tabular-nums truncate">
                    @if ($summary['contract_value'] > 0) {{ $fmt($summary['contract_value']) }} @else <span class="text-gray-300">—</span> @endif
                </p>
                <p class="text-xs text-gray-400">across {{ $summary['active_count'] }} active project{{ $summary['active_count'] === 1 ? '' : 's' }}</p>
            </div>

            {{-- Loan funded --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Loan funded</p>
                    <div class="shrink-0 w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center text-green-600">
                        <i data-lucide="banknote" class="h-4 w-4"></i>
                    </div>
                </div>
                <p class="text-xl font-medium text-green-600 mb-1 tabular-nums truncate" title="{{ $fmt($summary['total_collected']) }}">{{ $fmt($summary['total_collected']) }}</p>
                <p class="text-xs text-gray-400">disbursements into project accounts</p>
            </div>

            {{-- Total spent --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Total spent</p>
                    <div class="shrink-0 w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center text-red-600">
                        <i data-lucide="arrow-up-circle" class="h-4 w-4"></i>
                    </div>
                </div>
                <p class="text-xl font-medium text-red-600 mb-1 tabular-nums truncate" title="{{ $fmt($summary['total_outflow']) }}">{{ $fmt($summary['total_outflow']) }}</p>
                <p class="text-xs text-gray-400">
                    @if ($summary['contract_value'] > 0)
                        {{ $budgetUsedPct }}% of budget used
                    @else
                        POs, vendors, payroll
                    @endif
                </p>
            </div>

            {{-- Remaining / Net --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">{{ $summary['contract_value'] > 0 ? 'Remaining budget' : 'Net cash' }}</p>
                    <div class="shrink-0 w-8 h-8 rounded-lg {{ $positive ? 'bg-indigo-50 text-indigo-600' : 'bg-red-50 text-red-600' }} flex items-center justify-center">
                        <i data-lucide="wallet" class="h-4 w-4"></i>
                    </div>
                </div>
                <p class="text-xl font-medium mb-1 tabular-nums truncate {{ $positive ? 'text-indigo-600' : 'text-red-600' }}" title="{{ $fmt($summary['contract_value'] > 0 ? $remaining : $summary['net_cash']) }}">
                    {{ $fmt($summary['contract_value'] > 0 ? $remaining : $summary['net_cash']) }}
                </p>
                <p class="text-xs text-gray-400">{{ $summary['contract_value'] > 0 ? 'budget − spent' : 'inflow − outflow' }}</p>
            </div>
        @endif
    </div>

    {{-- ── In-house health insights strip ─────────────────────────────────── --}}
    @unless ($isExternal)
        @php
            $overCount    = $summary['over_budget']     ?? 0;
            $nearCount    = $summary['nearing_limit']   ?? 0;
            $activeCount  = $summary['active_recently'] ?? 0;
            $idleCount    = max(0, $summary['active_count'] - $activeCount);
            $hasInsights  = ($overCount + $nearCount + $idleCount) > 0;
        @endphp
        @if ($hasInsights || $activeCount > 0)
        <div class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2">
            <span class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Health</span>

            @if ($overCount > 0)
            <span class="inline-flex items-center gap-1 rounded-md bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700 ring-1 ring-red-100">
                <i data-lucide="alert-triangle" class="h-3 w-3"></i>
                {{ $overCount }} over budget
            </span>
            @endif

            @if ($nearCount > 0)
            <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-100">
                <i data-lucide="alert-circle" class="h-3 w-3"></i>
                {{ $nearCount }} nearing limit
            </span>
            @endif

            @if ($activeCount > 0)
            <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">
                <i data-lucide="activity" class="h-3 w-3"></i>
                {{ $activeCount }} active in last 30d
            </span>
            @endif

            @if ($idleCount > 0)
            <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200">
                <i data-lucide="moon" class="h-3 w-3"></i>
                {{ $idleCount }} idle
            </span>
            @endif

            @if ($overCount === 0 && $nearCount === 0 && $activeCount > 0)
            <span class="ml-auto inline-flex items-center gap-1 text-[11px] font-medium text-emerald-700">
                <i data-lucide="check-circle-2" class="h-3 w-3"></i>
                All active projects within budget
            </span>
            @endif
        </div>
        @endif
    @endunless

    {{-- ── Toolbar ─────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            @php
                $filters = [
                    'all'      => 'All',
                    'active'   => 'Active',
                    'on-hold'  => 'On hold',
                    'completed'=> 'Completed',
                ];
            @endphp
            @foreach ($filters as $val => $lbl)
            <button
                type="button"
                @click="statusFilter = '{{ $val }}'"
                :class="statusFilter === '{{ $val }}'
                    ? 'bg-blue-600 text-white border-blue-600 font-medium'
                    : 'bg-white text-gray-500 border-gray-200 hover:text-gray-700 hover:border-gray-300'"
                class="rounded-full border px-4 py-1.5 text-sm transition"
            >
                {{ $lbl }}
            </button>
            @endforeach
        </div>

        <div class="relative ml-auto">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400"></i>
            <input x-model="search" type="text" placeholder="Search projects or clients…"
                class="w-64 rounded-full border border-gray-200 py-1.5 pl-8 pr-3 text-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600" />
        </div>
    </div>

    {{-- ── Alerts ──────────────────────────────────────────────────────────── --}}
    @if (session('success'))
    <div class="rounded-md border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800">
        {{ session('success') }}
    </div>
    @endif
    @if ($errors->any())
    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
    @endif

    {{-- ── Project gallery ──────────────────────────────────────────────────── --}}
    @if ($projects->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-gray-200 bg-white py-16 text-center shadow-sm">
            <i data-lucide="folder-open" class="mb-3 h-8 w-8 text-gray-300"></i>
            <p class="text-sm text-gray-500">
                No projects yet. Click
                <strong>New {{ $isExternal ? 'project' : 'in-house project' }}</strong>
                to add one.
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($projects as $project)
            @php
                // ─── Money rollups (kind-aware) ─────────────────────────
                $collected = (float) $project->collections->sum('amount');
                $outflow   = (float) $project->expenses->sum('amount');
                $contract  = (float) $project->contract_value;
                $primaryAmt    = $isExternal ? $collected : $outflow;
                $primaryLabel  = $isExternal ? 'Collected' : 'Spent';
                $progressLabel = $isExternal ? 'Collection progress' : 'Budget utilization';

                $percentage = $contract > 0
                    ? round($primaryAmt / $contract * 100, 1)
                    : 0;
                $percentage = min(100, $percentage);

                // ─── Computed status key (due-state overrides project status) ───
                $isClosed = in_array($project->status, ['completed', 'cancelled']);
                $status   = null;
                if ($project->due_date && ! $isClosed) {
                    $days = $today->diffInDays($project->due_date, false);
                    if ($days < 0)        { $status = 'overdue'; }
                    elseif ($days <= 14)  { $status = 'soon'; }
                }
                if (! $status) {
                    $status = match ($project->status) {
                        'completed'                         => 'completed',
                        'on-hold', 'cancelled'              => 'on_hold',
                        'active', 'in_progress', 'planning' => 'active',
                        default                             => 'active',
                    };
                }

                $accentColor = match ($status) {
                    'overdue'   => '#EF4444',
                    'soon'      => '#F59E0B',
                    'active'    => '#22C55E',
                    'on_hold'   => '#94A3B8',
                    'completed' => '#185FA5',
                    default     => '#185FA5',
                };

                $statusClass = match ($status) {
                    'overdue'   => 'bg-red-100 text-red-800',
                    'soon'      => 'bg-amber-100 text-amber-800',
                    'active'    => 'bg-green-100 text-green-700',
                    'on_hold'   => 'bg-gray-100 text-gray-600',
                    'completed' => 'bg-blue-100 text-blue-700',
                    default     => 'bg-blue-100 text-blue-700',
                };

                $statusLabel = match ($status) {
                    'overdue'   => 'OVERDUE',
                    'soon'      => 'SOON',
                    'active'    => 'ACTIVE',
                    'on_hold'   => 'ON HOLD',
                    'completed' => 'DONE',
                    default     => strtoupper($status),
                };

                // Alpine filter group (must match existing filter tab values: all/active/on-hold/completed)
                $statusGroup = match ($project->status) {
                    'active', 'in_progress', 'planning' => 'active',
                    'on-hold'   => 'on-hold',
                    'completed' => 'completed',
                    default     => 'other',
                };

                // External: green if any collected; In-house: red if any spent — gray when zero.
                $primaryAmtClass = $primaryAmt > 0
                    ? ($isExternal ? 'text-green-600' : 'text-red-600')
                    : 'text-gray-400';
            @endphp
            <a
                href="{{ route('projects.show', $project) }}"
                x-show="(statusFilter === 'all' || statusFilter === '{{ $statusGroup }}')
                    && (search === '' || '{{ addslashes(strtolower($project->name)) }}'.includes(search.toLowerCase()) || '{{ addslashes(strtolower($project->client_name ?? '')) }}'.includes(search.toLowerCase()))"
                class="block bg-white border border-gray-200 rounded-xl overflow-hidden cursor-pointer transition-all duration-150 hover:shadow-lg hover:-translate-y-0.5"
                style="border-top: 3px solid {{ $accentColor }}"
            >
                {{-- Zone 1: Top --}}
                <div class="px-4 pt-4 pb-3 border-b border-gray-100">
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $project->name }}</p>
                            <p class="text-xs text-gray-400 mt-0.5 truncate">
                                @if ($isExternal)
                                    {{ $project->client_name ?: 'No client' }}@if ($project->location) · {{ $project->location }} @endif
                                @else
                                    {{ $project->location ?: 'No location' }}
                                @endif
                            </p>
                        </div>
                        <span class="shrink-0 text-xs font-bold px-2.5 py-1 rounded-full whitespace-nowrap {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                    </div>

                    {{-- Progress bar --}}
                    <div>
                        <div class="flex justify-between mb-1.5">
                            <span class="text-xs text-gray-400">{{ $progressLabel }}</span>
                            <span class="text-xs font-semibold text-gray-700">{{ $percentage }}%</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300"
                                 style="width: {{ $percentage }}%; background-color: {{ $accentColor }}"></div>
                        </div>
                    </div>
                </div>

                {{-- Zone 2: Stats --}}
                <div class="grid grid-cols-2 divide-x divide-gray-100">
                    <div class="min-w-0 px-4 py-3">
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">{{ $isExternal ? 'Contract' : 'Budget' }}</p>
                        <p class="text-sm font-medium text-gray-900 tabular-nums truncate" title="{{ $contract > 0 ? $fmt($contract) : '—' }}">{{ $contract > 0 ? $fmt($contract) : '—' }}</p>
                    </div>
                    <div class="min-w-0 px-4 py-3">
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">{{ $primaryLabel }}</p>
                        <p class="text-sm font-medium tabular-nums truncate {{ $primaryAmtClass }}" title="{{ $fmt($primaryAmt) }}">{{ $fmt($primaryAmt) }}</p>
                    </div>
                </div>

                {{-- Zone 3: Footer --}}
                <div class="flex items-center justify-between gap-2 px-4 py-2.5 bg-gray-50 border-t border-gray-100">
                    <span class="min-w-0 text-xs text-gray-400 flex items-center gap-1 truncate">
                        <i data-lucide="calendar" class="shrink-0 w-3 h-3"></i>
                        <span class="truncate">{{ $project->due_date ? $project->due_date->format('M d, Y') : 'No due date' }}</span>
                    </span>
                    <span class="shrink-0 text-xs font-semibold text-blue-600 flex items-center gap-1">
                        Open
                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </span>
                </div>
            </a>
            @endforeach
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         ADD NEW PROJECT MODAL
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div
        x-show="showAdd"
        x-cloak
        style="display:none"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @keydown.escape.window="showAdd = false"
    >
        <div
            @click.outside="showAdd = false"
            class="w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-xl"
            style="max-height:90vh"
        >
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h3 class="text-base font-semibold text-omet-navy">
                    New {{ $isExternal ? 'project' : 'in-house project' }}
                </h3>
                <button @click="showAdd = false" class="rounded-md p-1 text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('projects.store') }}" enctype="multipart/form-data" class="space-y-4 px-6 py-5">
                @csrf

                <input type="hidden" name="kind" value="{{ $kind ?? 'external' }}">

                <div class="grid grid-cols-2 gap-4">
                    {{-- Cover image --}}
                    <div class="col-span-2">
                        <x-label for="m_image" :value="__('Cover image (optional)')" />
                        <label for="m_image"
                               class="mt-1 flex aspect-[16/9] cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-slate-200 bg-slate-50 text-center transition hover:border-omet-blue hover:bg-slate-100">
                            <template x-if="addPreview">
                                <img :src="addPreview" alt="" class="h-full w-full object-cover" />
                            </template>
                            <template x-if="!addPreview">
                                <div class="flex flex-col items-center gap-1 text-slate-400">
                                    <i data-lucide="image-plus" class="h-6 w-6"></i>
                                    <span class="text-xs font-medium">Click to upload</span>
                                    <span class="text-[10.5px] text-slate-400">JPG, PNG, or WEBP · up to 4 MB</span>
                                </div>
                            </template>
                        </label>
                        <input id="m_image" type="file" name="image" accept="image/jpeg,image/png,image/webp"
                               class="hidden" @change="previewFile($event, 'addPreview')" />
                    </div>
                    <div class="col-span-2">
                        <x-label for="m_name" :value="__('Project name *')" />
                        <x-input id="m_name" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" type="text" name="name" :value="old('name')" required autofocus />
                    </div>
                    <div class="col-span-2">
                        <x-label for="m_status" :value="__('Status')" />
                        <select id="m_status" name="status" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-omet-blue focus:ring-omet-blue">
                            @php $sel = old('status', 'active'); @endphp
                            @foreach ($statusLabels as $val => $lbl)
                                <option value="{{ $val }}" {{ $sel === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="kind === 'external'" x-cloak>
                        <x-label for="m_client" :value="__('Client name')" />
                        <x-input id="m_client" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" type="text" name="client_name" :value="old('client_name')" x-bind:required="kind === 'external'" />
                        <p class="mt-1 text-xs text-gray-500">Required for external projects.</p>
                    </div>
                    <div :class="kind === 'external' ? '' : 'col-span-2'">
                        <x-label for="m_location" :value="__('Location')" />
                        <x-input id="m_location" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" type="text" name="location" :value="old('location')" />
                    </div>
                    <div class="col-span-2">
                        <x-label for="m_cv"
                                 :value="(($kind ?? 'external') === 'external') ? __('Contract value (PHP)') : __('Budget (PHP) — optional')" />
                        <div class="relative mt-1">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-gray-400">₱</span>
                            <x-input id="m_cv" class="block w-full rounded-lg border-gray-300 pl-7 text-sm" type="number" name="contract_value" :value="old('contract_value', 0)" min="0" step="0.01" />
                        </div>
                        @unless ($isExternal)
                        <p class="mt-1 text-xs text-gray-400">Set a budget to track utilization; leave blank to just record spending.</p>
                        @endunless
                    </div>
                    <div>
                        <x-label for="m_start" :value="__('Start date')" />
                        <x-input id="m_start" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" type="date" name="start_date" :value="old('start_date')" />
                    </div>
                    <div>
                        <x-label for="m_end" :value="__('End date')" />
                        <x-input id="m_end" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" type="date" name="end_date" :value="old('end_date')" />
                    </div>
                    <div class="col-span-2">
                        <x-label for="m_due" :value="__('Due date')" />
                        <x-input id="m_due" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" type="date" name="due_date" :value="old('due_date')" />
                        <p class="mt-1 text-xs text-gray-400">Target delivery date — can differ from end date.</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showAdd = false"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-omet-blue px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-omet-lightblue">
                        Create project
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         PROJECT IMAGE UPLOAD MODAL
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div
        x-show="showImageModal"
        x-cloak
        style="display:none"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @keydown.escape.window="showImageModal = false"
    >
        <div
            @click.outside="showImageModal = false"
            class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl"
        >
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <div>
                    <h3 class="text-base font-semibold text-omet-navy">Update project image</h3>
                    <p class="text-xs text-gray-400" x-text="imageProjectName"></p>
                </div>
                <button type="button" @click="showImageModal = false" class="rounded-md p-1 text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <form method="POST"
                  :action="'{{ url('projects') }}/' + imageProjectId + '/image'"
                  enctype="multipart/form-data"
                  class="space-y-4 px-6 py-5">
                @csrf

                <label for="img_input"
                       class="flex aspect-[16/9] cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-slate-200 bg-slate-50 text-center transition hover:border-omet-blue hover:bg-slate-100">
                    <template x-if="imagePreview">
                        <img :src="imagePreview" alt="" class="h-full w-full object-cover" />
                    </template>
                    <template x-if="!imagePreview">
                        <div class="flex flex-col items-center gap-1 text-slate-400">
                            <i data-lucide="image-plus" class="h-6 w-6"></i>
                            <span class="text-xs font-medium">Click to choose an image</span>
                            <span class="text-[10.5px] text-slate-400">JPG, PNG, or WEBP · up to 4 MB</span>
                        </div>
                    </template>
                </label>
                <input id="img_input" type="file" name="image" accept="image/jpeg,image/png,image/webp" required
                       class="hidden" @change="previewFile($event, 'imagePreview')" />

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="showImageModal = false"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        :disabled="!imagePreview"
                        class="rounded-lg bg-omet-blue px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-omet-lightblue disabled:cursor-not-allowed disabled:opacity-50">
                        Save image
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>{{-- /x-data --}}
</x-app-layout>
