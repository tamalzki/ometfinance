@props(['paginator'])

@if ($paginator->hasPages())
<nav class="no-print flex flex-col gap-2 border-t border-slate-100 px-2 py-3 sm:flex-row sm:items-center sm:justify-between" aria-label="Pagination">
    <p class="text-[11px] text-slate-500">
        @if ($paginator->total() > 0)
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        @else
            No results
        @endif
    </p>
    <div class="flex flex-wrap items-center gap-1.5">
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] font-medium text-slate-400">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}"
               class="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-600 shadow-sm transition hover:border-omet-blue hover:text-omet-blue">Previous</a>
        @endif
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}"
               class="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-600 shadow-sm transition hover:border-omet-blue hover:text-omet-blue">Next</a>
        @else
            <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] font-medium text-slate-400">Next</span>
        @endif
    </div>
</nav>
@endif
