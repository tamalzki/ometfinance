<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'OMET Finance System') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://unpkg.com/lucide@latest"></script>

        @include('partials.mix-stylesheet')
        <script src="{{ mix('js/app.js') }}" defer></script>
        <style>
            [x-cloak]{display:none!important}
            main{scrollbar-gutter:stable}
        </style>
    </head>
    <body class="font-sans antialiased text-slate-700" x-data="{ sidebarOpen: false }">
        <div class="h-screen overflow-hidden bg-slate-50 lg:flex">
            <x-sidebar />
            <script>if (typeof lucide !== 'undefined') { lucide.createIcons(); }</script>

            <div class="flex h-screen min-h-0 min-w-0 flex-1 flex-col lg:ml-64">
                <x-header :title="$pageTitle ?? 'Dashboard'" />

                <main class="flex min-h-0 min-w-0 flex-1 flex-col overflow-y-auto p-4 sm:p-6 lg:p-7">
                    {{ $slot }}
                </main>
            </div>

            <div
                x-show="sidebarOpen"
                @click="sidebarOpen = false"
                class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
                x-transition.opacity
                style="display: none;"
            ></div>
        </div>

        <script>
            lucide.createIcons();
        </script>
    </body>
</html>
