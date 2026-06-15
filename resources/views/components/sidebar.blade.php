@php
    $sections = [
        [
            'label' => 'Workspace',
            'links' => [
                ['name' => 'Dashboard', 'route' => 'dashboard',      'icon' => 'layout-dashboard'],
                ['name' => 'Accounts',  'route' => 'accounts.index', 'icon' => 'landmark', 'pattern' => 'accounts.*'],
                ['name' => 'Categories', 'route' => 'categories.index', 'icon' => 'tags', 'pattern' => 'categories.*'],
            ],
        ],
        [
            'label' => 'Projects',
            'group' => [
                'key'  => 'projects',
                'icon' => 'folders',
                'pattern' => 'projects.*',
            ],
            'links' => [
                ['name' => 'External Projects', 'route' => 'projects.external', 'icon' => 'folder-open', 'pattern' => 'projects.external', 'showKind' => 'external'],
                ['name' => 'In-house Projects', 'route' => 'projects.in_house', 'icon' => 'building-2',  'pattern' => 'projects.in_house', 'showKind' => 'in_house'],
            ],
        ],
        [
            'label' => 'Cash Movement',
            'links' => [
                ['name' => 'Transfers / Intercompany', 'route' => 'transfers.index', 'icon' => 'arrow-left-right', 'pattern' => 'transfers.*'],
            ],
        ],
        [
            'label' => 'Disbursements',
            'links' => [
                ['name' => 'Daily Transactions', 'route' => 'vouchers.index', 'icon' => 'receipt', 'pattern' => 'vouchers.index'],
                ['name' => 'Payables',  'route' => 'vouchers.payables', 'icon' => 'alarm-clock', 'pattern' => 'vouchers.payables'],
            ],
        ],
        [
            'label' => 'Insights',
            'links' => [
                ['name' => 'Reports', 'route' => 'reports', 'icon' => 'bar-chart-3'],
            ],
        ],
    ];

    // On project detail pages (projects.show.*), the route name alone doesn't
    // tell us whether the project is external or in-house — check the bound model.
    $currentProject     = request()->route('project');
    $currentProjectKind = $currentProject instanceof \App\Models\Project ? $currentProject->kind : null;

    $isLinkActive = function (array $link) use ($currentProjectKind): bool {
        $patterns = isset($link['pattern']) ? explode('|', $link['pattern']) : [$link['route']];
        foreach ($patterns as $p) {
            if (request()->routeIs($p)) {
                return true;
            }
        }
        if (isset($link['showKind']) && request()->routeIs('projects.show*') && $currentProjectKind === $link['showKind']) {
            return true;
        }
        return false;
    };

    $user        = auth()->user();
    $userInitial = strtoupper(substr($user->name ?? 'U', 0, 1));

    // CFO sees Dashboard, Categories, Projects, Disbursements, and Reports — not Accounts or Transfers.
    if ($user->isCfo()) {
        // Keep Dashboard and Categories from Workspace (remove Accounts)
        $sections[0]['links'] = array_values(
            array_filter($sections[0]['links'], fn ($l) => in_array($l['route'], ['dashboard', 'categories.index'], true))
        );
        // Remove Cash Movement / Transfers (index 2) — Projects (index 1) stays
        $sections = array_values(
            array_filter($sections, fn ($s, $i) => $i !== 2, ARRAY_FILTER_USE_BOTH)
        );
    }
@endphp

<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-40 flex h-screen w-64 flex-col border-r border-white/5 bg-[#0B1726] transition-transform duration-200 ease-in-out lg:translate-x-0 lg:transition-none"
>
    {{-- Brand --}}
    <div class="flex items-center gap-3 px-5 pb-5 pt-6">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/5 ring-1 ring-white/10">
            <span class="text-[13px] font-bold tracking-tight text-white">OM</span>
        </span>
        <div class="min-w-0 leading-tight">
            <p class="truncate text-[15px] font-semibold tracking-tight text-white">OMET</p>
            <p class="truncate text-[11px] font-medium text-slate-400">Finance System</p>
        </div>
    </div>

    <div class="mx-5 h-px bg-white/10"></div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4" @click="if (window.innerWidth < 1024) sidebarOpen = false">
        @foreach ($sections as $sectionIndex => $section)
            @php
                $isGroup = isset($section['group']);
                $groupActive = $isGroup ? request()->routeIs($section['group']['pattern']) : false;
            @endphp

            @if ($isGroup)
                @php $storageKey = 'sidebar.group.' . $section['group']['key']; @endphp
                <div
                    x-data="{
                        open: (() => {
                            const saved = localStorage.getItem('{{ $storageKey }}');
                            return saved === null ? {{ $groupActive ? 'true' : 'true' }} : saved === '1';
                        })()
                    }"
                    x-init="$watch('open', v => localStorage.setItem('{{ $storageKey }}', v ? '1' : '0'))"
                    @class([
                        'pb-1',
                        'pt-1' => $sectionIndex === 0,
                        'pt-4' => $sectionIndex !== 0,
                    ])
                >
                    <button
                        type="button"
                        @click="open = !open"
                        @class([
                            'group flex w-full items-center gap-2 rounded-md px-3 pb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] transition',
                            'text-slate-300' => $groupActive,
                            'text-slate-500 hover:text-slate-300' => ! $groupActive,
                        ])
                    >
                        <i data-lucide="{{ $section['group']['icon'] }}"
                           @class([
                               'h-3.5 w-3.5 shrink-0',
                               'text-[#60A5FA]' => $groupActive,
                               'text-slate-500 group-hover:text-slate-300' => ! $groupActive,
                           ])></i>
                        <span class="flex-1 text-left">{{ $section['label'] }}</span>
                        <i data-lucide="chevron-down"
                           class="h-3 w-3 shrink-0 text-slate-500 transition-transform"
                           :class="open ? 'rotate-0' : '-rotate-90'"></i>
                    </button>

                    <div class="space-y-0.5" :class="{ 'hidden': !open }">
                        @foreach ($section['links'] as $link)
                            @php $active = $isLinkActive($link); @endphp
                            <a href="{{ route($link['route']) }}"
                                @class([
                                    'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium transition',
                                    'bg-white/[0.06] text-white' => $active,
                                    'text-slate-300 hover:bg-white/[0.04] hover:text-white' => ! $active,
                                ])>
                                @if ($active)
                                    <span class="absolute inset-y-1.5 left-0 w-[3px] rounded-r-full bg-[#3B82F6]"></span>
                                @endif
                                <i data-lucide="{{ $link['icon'] }}"
                                    @class([
                                        'h-4 w-4 shrink-0',
                                        'text-[#60A5FA]' => $active,
                                        'text-slate-400 group-hover:text-slate-200' => ! $active,
                                    ])></i>
                                <span class="truncate">{{ $link['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                <p @class([
                    'px-3 pb-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500',
                    'pt-1' => $sectionIndex === 0,
                    'pt-4' => $sectionIndex !== 0,
                ])>
                    {{ $section['label'] }}
                </p>

                <div class="space-y-0.5">
                    @foreach ($section['links'] as $link)
                        @php $active = $isLinkActive($link); @endphp
                        <a href="{{ route($link['route']) }}"
                            @class([
                                'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium transition',
                                'bg-white/[0.06] text-white' => $active,
                                'text-slate-300 hover:bg-white/[0.04] hover:text-white' => ! $active,
                            ])>
                            @if ($active)
                                <span class="absolute inset-y-1.5 left-0 w-[3px] rounded-r-full bg-[#3B82F6]"></span>
                            @endif
                            <i data-lucide="{{ $link['icon'] }}"
                                @class([
                                    'h-4 w-4 shrink-0',
                                    'text-[#60A5FA]' => $active,
                                    'text-slate-400 group-hover:text-slate-200' => ! $active,
                                ])></i>
                            <span class="truncate">{{ $link['name'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>

    {{-- User --}}
    <div class="border-t border-white/10 px-3 py-3">
        <div class="flex items-center gap-3 rounded-lg px-2 py-2">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-[12px] font-semibold text-white ring-1 ring-white/10">
                {{ $userInitial }}
            </span>
            <div class="min-w-0 flex-1 leading-tight">
                <p class="truncate text-[12.5px] font-semibold text-white">{{ $user->name }}</p>
                <p class="truncate text-[11px] text-slate-400">
                    {{ $user->isCfo() ? 'CFO' : 'Admin' }} · {{ $user->email }}
                </p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <button type="submit"
                        title="Sign out"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-400 transition hover:bg-white/5 hover:text-white">
                    <i data-lucide="log-out" class="h-4 w-4"></i>
                </button>
            </form>
        </div>
    </div>
</aside>
