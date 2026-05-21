@props(['module'])

<x-app-layout :page-title="$module">
    <div class="flex min-h-[70vh] items-center justify-center rounded-lg bg-omet-card p-6 shadow-md">
        <div class="text-center">
            <i data-lucide="construction" class="mx-auto mb-4 h-10 w-10 text-omet-blue"></i>
            <h2 class="text-2xl font-bold text-omet-navy">{{ $module }}</h2>
            <p class="mt-2 text-sm text-gray-500">Coming Soon</p>
            <p class="mt-1 text-xs text-gray-400">TODO: Build this module with live financial data and workflows.</p>
        </div>
    </div>
</x-app-layout>
