@props([
    'title'   => '',
    'value'   => '',
    'icon'    => 'circle',
    'color'   => 'blue',
    'subtext' => null,
])

@php
    $palette = [
        'blue'   => ['bg' => 'bg-blue-50',    'text' => 'text-blue-600'],
        'green'  => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600'],
        'red'    => ['bg' => 'bg-rose-50',    'text' => 'text-rose-600'],
        'amber'  => ['bg' => 'bg-amber-50',   'text' => 'text-amber-600'],
        'yellow' => ['bg' => 'bg-amber-50',   'text' => 'text-amber-600'],
        'slate'  => ['bg' => 'bg-slate-100',  'text' => 'text-slate-600'],
    ];

    $tone = $palette[$color] ?? $palette['blue'];
@endphp

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $title }}</p>
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $tone['bg'] }} {{ $tone['text'] }}">
            <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>
        </span>
    </div>

    <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight text-slate-900">{{ $value }}</p>

    @if ($subtext)
        <p class="mt-1 text-[11.5px] text-slate-500">{{ $subtext }}</p>
    @endif
</div>
