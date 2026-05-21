<x-guest-layout>
    <div class="flex min-h-screen items-center justify-center bg-[#0B1726] px-6 py-12">
        <div class="w-full max-w-[440px]">

            <div class="mb-6 flex items-center justify-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/5 ring-1 ring-white/10">
                    <span class="text-[17px] font-bold tracking-tight text-white">O</span>
                </span>
                <div class="leading-tight">
                    <p class="text-[15.5px] font-semibold tracking-tight text-white">OMET</p>
                    <p class="text-[11px] font-medium text-slate-400">Finance Management System</p>
                </div>
            </div>

            <div class="rounded-2xl border border-white/5 bg-white p-8 text-center shadow-2xl shadow-black/40">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3h.008v.008H12V15.75zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>

                <h1 class="mt-5 text-[18px] font-semibold tracking-tight text-slate-900">Invite link is invalid</h1>
                <p class="mt-2 text-[13px] leading-relaxed text-slate-500">
                    This setup link is missing, has expired, or has already been used.
                    Ask an existing administrator to issue a new invite via
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-[12px] text-slate-700">php artisan admin:invite</code>.
                </p>

                <a href="{{ route('login') }}"
                   class="mt-6 inline-flex h-[40px] items-center justify-center rounded-lg bg-[#0B1726] px-5 text-[13px] font-semibold text-white transition hover:bg-[#152439]">
                    Back to sign in
                </a>
            </div>

            <p class="mt-5 text-center text-[11px] text-slate-500">
                © {{ now()->format('Y') }} OMET · Finance Management System
            </p>
        </div>
    </div>
</x-guest-layout>
