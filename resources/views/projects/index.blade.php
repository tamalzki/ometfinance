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
    class="flex min-h-0 min-w-0 flex-1 flex-col gap-2"
>

    {{-- ── Alerts ──────────────────────────────────────────────────────────── --}}
    @if (session('success'))
    <div class="shrink-0 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-xs font-medium text-green-800">
        {{ session('success') }}
    </div>
    @endif
    @if ($errors->any())
    <div class="shrink-0 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
    @endif

    @php
        if (! $isExternal) {
            $budgetUsedPct = $summary['contract_value'] > 0
                ? min(100, round($summary['total_outflow'] / $summary['contract_value'] * 100, 1))
                : 0;
            $remaining = max(0, $summary['contract_value'] - $summary['total_outflow']);
            $positive = $summary['contract_value'] > 0 ? $remaining > 0 : $summary['net_cash'] >= 0;
        } else {
            $netPositive = $summary['net_cash'] >= 0;
        }
    @endphp

    {{-- ── Top bar: title + KPIs + action ─────────────────────────────────── --}}
    <div class="flex shrink-0 flex-wrap items-stretch divide-x divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex min-w-[148px] flex-col justify-center px-4 py-3">
            <p class="text-[13px] font-bold tracking-tight text-omet-navy">{{ $kindLabel ?? 'Projects' }}</p>
            <p class="mt-0.5 text-[11px] text-slate-400">
                {{ $summary['active_count'] }} active · {{ $summary['total_count'] }} total
                @if ($summary['completed_count'] > 0) · {{ $summary['completed_count'] }} done @endif
            </p>
        </div>

        @if ($isExternal)
            <div class="flex flex-col justify-center px-5 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Contract value</p>
                <p class="mt-1 text-base font-bold tabular-nums text-blue-600" title="{{ $fmt($summary['contract_value']) }}">{{ $fmt($summary['contract_value']) }}</p>
            </div>
            <div class="flex flex-col justify-center px-5 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Collected</p>
                <p class="mt-1 text-base font-bold tabular-nums text-emerald-600" title="{{ $fmt($summary['total_collected']) }}">{{ $fmt($summary['total_collected']) }}</p>
                <p class="text-[10px] text-slate-400">{{ $summary['collection_pct'] }}% of contract</p>
            </div>
            <div class="flex flex-col justify-center px-5 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Outflow</p>
                <p class="mt-1 text-base font-bold tabular-nums text-red-600" title="{{ $fmt($summary['total_outflow']) }}">{{ $fmt($summary['total_outflow']) }}</p>
            </div>
            <div class="flex flex-col justify-center px-5 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Net cash</p>
                <p class="mt-1 text-base font-bold tabular-nums {{ $netPositive ? 'text-emerald-600' : 'text-red-600' }}" title="{{ $fmt($summary['net_cash']) }}">{{ $fmt($summary['net_cash']) }}</p>
            </div>
        @else
            <div class="flex flex-col justify-center px-5 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total budget</p>
                <p class="mt-1 text-base font-bold tabular-nums text-indigo-600">
                    @if ($summary['contract_value'] > 0) {{ $fmt($summary['contract_value']) }} @else <span class="text-slate-300">—</span> @endif
                </p>
            </div>
            <div class="flex flex-col justify-center px-5 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Funded</p>
                <p class="mt-1 text-base font-bold tabular-nums text-emerald-600" title="{{ $fmt($summary['total_collected']) }}">{{ $fmt($summary['total_collected']) }}</p>
            </div>
            <div class="flex flex-col justify-center px-5 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total spent</p>
                <p class="mt-1 text-base font-bold tabular-nums text-red-600" title="{{ $fmt($summary['total_outflow']) }}">{{ $fmt($summary['total_outflow']) }}</p>
                @if ($summary['contract_value'] > 0)
                    <p class="text-[10px] text-slate-400">{{ $budgetUsedPct }}% of budget</p>
                @endif
            </div>
            <div class="flex flex-col justify-center px-5 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ $summary['contract_value'] > 0 ? 'Remaining' : 'Net cash' }}</p>
                <p class="mt-1 text-base font-bold tabular-nums {{ $positive ? 'text-indigo-600' : 'text-red-600' }}" title="{{ $fmt($summary['contract_value'] > 0 ? $remaining : $summary['net_cash']) }}">
                    {{ $fmt($summary['contract_value'] > 0 ? $remaining : $summary['net_cash']) }}
                </p>
            </div>
        @endif

        <div class="flex-1"></div>

        <div class="flex items-center px-4 py-3">
            <button type="button" @click="openAddModal()"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-omet-blue px-3.5 py-2 text-[12.5px] font-semibold text-white shadow-sm transition hover:bg-omet-lightblue">
                <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                New {{ $isExternal ? 'project' : 'in-house project' }}
            </button>
        </div>
    </div>

    {{-- ── Toolbar ─────────────────────────────────────────────────────────── --}}
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2">
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
            <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
            <input x-model="search" type="search" autocomplete="off" placeholder="Search projects or clients…"
                class="h-8 w-64 rounded-md border border-slate-200 bg-white pl-8 pr-3 text-[12px] text-slate-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15" />
        </div>
    </div>

    {{-- ── Project table ─────────────────────────────────────────────────────── --}}
    @if ($projects->isEmpty())
        <div class="flex shrink-0 flex-col items-center justify-center rounded-xl border border-gray-200 bg-white py-16 text-center shadow-sm">
            <i data-lucide="folder-open" class="mb-3 h-8 w-8 text-gray-300"></i>
            <p class="text-sm text-gray-500">
                No projects yet. Click
                <strong>New {{ $isExternal ? 'project' : 'in-house project' }}</strong>
                to add one.
            </p>
        </div>
    @else
        <div class="data-grid min-h-0 min-w-0 flex-1 overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-20">
                        <tr>
                            <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Project</th>
                            <th class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:table-cell">Progress</th>
                            <th class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 md:table-cell">{{ $isExternal ? 'Contract value' : 'Budget' }}</th>
                            <th class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 md:table-cell">{{ $isExternal ? 'Collected' : 'Spent' }}</th>
                            @if ($isExternal)
                            <th class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 md:table-cell">Running Cost</th>
                            @endif
                            <th class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 lg:table-cell">{{ $isExternal ? 'Outstanding' : 'Remaining / Net' }}</th>
                            <th class="hidden border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 xl:table-cell">Due date</th>
                            <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th class="border-b border-slate-200 bg-slate-50 px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($projects as $project)
                        @php
                            // ─── Money rollups (kind-aware) ─────────────────────────
                            $collected = (float) $project->collections->sum('amount');
                            $outflow   = (float) $project->expenses->sum('amount');
                            $contract  = (float) $project->contract_value;
                            $primaryAmt    = $isExternal ? $collected : $outflow;
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

                            // ─── Net / Remaining / Outstanding (kind-aware) ─────────
                            if ($isExternal) {
                                $netLabel = 'Outstanding';
                                $netVal   = max(0, $contract - $collected);
                                $netClass = $netVal > 0 ? 'text-amber-600' : 'text-gray-400';
                            } elseif ($contract > 0) {
                                $netLabel = 'Remaining';
                                $netVal   = max(0, $contract - $outflow);
                                $netClass = $netVal > 0 ? 'text-indigo-600' : 'text-red-600';
                            } else {
                                $netLabel = 'Net cash';
                                $netVal   = $collected - $outflow;
                                $netClass = $netVal >= 0 ? 'text-indigo-600' : 'text-red-600';
                            }
                        @endphp
                        <tr
                            x-show="(statusFilter === 'all' || statusFilter === '{{ $statusGroup }}')
                                && (search === '' || '{{ addslashes(strtolower($project->name)) }}'.includes(search.toLowerCase()) || '{{ addslashes(strtolower($project->client_name ?? '')) }}'.includes(search.toLowerCase()))"
                            @click="window.location.href = '{{ route('projects.show', $project) }}'"
                            class="group cursor-pointer transition hover:bg-blue-50/40"
                        >
                            {{-- Name + client/location --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="h-8 w-1 shrink-0 rounded-full" style="background-color: {{ $accentColor }}"></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $project->name }}</p>
                                        <p class="mt-0.5 truncate text-xs text-gray-400">
                                            @if ($isExternal)
                                                {{ $project->client_name ?: 'No client' }}@if ($project->location) · {{ $project->location }} @endif
                                            @else
                                                {{ $project->location ?: 'No location' }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </td>

                            {{-- Progress bar --}}
                            <td class="hidden px-4 py-3 sm:table-cell">
                                <div class="flex w-36 items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                                        <div class="h-full rounded-full transition-all duration-300"
                                             style="width: {{ $percentage }}%; background-color: {{ $accentColor }}"></div>
                                    </div>
                                    <span class="w-9 shrink-0 text-right text-xs font-semibold tabular-nums text-gray-700">{{ $percentage }}%</span>
                                </div>
                                <p class="mt-1 text-[10.5px] text-gray-400">{{ $progressLabel }}</p>
                            </td>

                            {{-- Contract / Budget --}}
                            <td class="hidden px-4 py-3 text-right tabular-nums md:table-cell">
                                <span class="font-medium text-gray-900" title="{{ $contract > 0 ? $fmt($contract) : '—' }}">{{ $contract > 0 ? $fmt($contract) : '—' }}</span>
                            </td>

                            {{-- Collected / Spent --}}
                            <td class="hidden px-4 py-3 text-right tabular-nums md:table-cell">
                                <span class="font-medium {{ $primaryAmtClass }}" title="{{ $fmt($primaryAmt) }}">{{ $fmt($primaryAmt) }}</span>
                            </td>

                            @if ($isExternal)
                            {{-- Running cost (total outflow to date) --}}
                            <td class="hidden px-4 py-3 text-right tabular-nums md:table-cell">
                                <span class="font-medium {{ $outflow > 0 ? 'text-red-600' : 'text-gray-400' }}" title="{{ $fmt($outflow) }}">{{ $fmt($outflow) }}</span>
                            </td>
                            @endif

                            {{-- Outstanding / Remaining / Net cash --}}
                            <td class="hidden px-4 py-3 text-right tabular-nums lg:table-cell">
                                <span class="font-medium {{ $netClass }}" title="{{ $fmt($netVal) }}">{{ $fmt($netVal) }}</span>
                                <p class="mt-0.5 text-[10.5px] text-gray-400">{{ $netLabel }}</p>
                            </td>

                            {{-- Due date --}}
                            <td class="hidden px-4 py-3 xl:table-cell">
                                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                                    <i data-lucide="calendar" class="h-3 w-3 shrink-0 text-gray-400"></i>
                                    <span class="truncate">{{ $project->due_date ? $project->due_date->format('M d, Y') : '—' }}</span>
                                </div>
                            </td>

                            {{-- Status badge --}}
                            <td class="px-4 py-3">
                                <span class="inline-flex shrink-0 whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>

                            {{-- Action --}}
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('projects.destroy', $project) }}"
                                          onsubmit="return confirm('Delete project &quot;{{ addslashes($project->name) }}&quot;? This permanently removes its inflow/outflow entries and allocation lines. Linked vouchers and transfers will be kept but unlinked from this project.');"
                                          class="inline-flex shrink-0" @click.stop>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Delete project"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-red-200 bg-red-50 text-red-600 shadow-sm transition hover:bg-red-100">
                                            <i data-lucide="trash-2" class="pointer-events-none h-3.5 w-3.5"></i>
                                        </button>
                                    </form>
                                    <i data-lucide="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-omet-blue"></i>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
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
