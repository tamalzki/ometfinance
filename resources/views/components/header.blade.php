@props(['title' => 'Dashboard'])

@php
    $user        = auth()->user();
    $userInitial = strtoupper(substr($user->name ?? 'U', 0, 1));
@endphp

<header class="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
    <div class="flex min-w-0 items-center gap-3">
        <button
            type="button"
            @click="sidebarOpen = true"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 lg:hidden"
            aria-label="Open sidebar"
        >
            <i data-lucide="menu" class="h-4 w-4"></i>
        </button>
        <h2 class="truncate text-[15px] font-semibold tracking-tight text-slate-900">{{ $title }}</h2>
    </div>

    <div class="relative shrink-0" x-data="{ open: false }">
        <button
            type="button"
            @click="open = !open"
            class="flex items-center gap-2.5 rounded-lg border border-transparent px-1.5 py-1 transition hover:border-slate-200 hover:bg-slate-50"
        >
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-omet-blue text-[12px] font-semibold text-white">
                {{ $userInitial }}
            </span>
            <span class="hidden text-[13px] font-medium text-slate-700 sm:block">{{ $user->name }}</span>
            <i data-lucide="chevron-down" class="h-3.5 w-3.5 text-slate-400"></i>
        </button>

        <div
            x-show="open"
            @click.outside="open = false"
            x-transition.origin.top.right
            class="absolute right-0 mt-2 w-48 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
            style="display: none;"
        >
            <div class="border-b border-slate-100 px-3 py-2">
                <p class="truncate text-[12.5px] font-semibold text-slate-800">{{ $user->name }}</p>
                <p class="truncate text-[11px] text-slate-500">{{ $user->email }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-[13px] font-medium text-slate-700 transition hover:bg-slate-50 hover:text-slate-900">
                    <i data-lucide="log-out" class="h-3.5 w-3.5 text-slate-400"></i>
                    Sign out
                </button>
            </form>
        </div>
    </div>
</header>
