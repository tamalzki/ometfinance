<x-guest-layout>
    <div class="min-h-screen bg-[#0B1726] lg:grid lg:grid-cols-[1.1fr_1fr]">

        {{-- ── LEFT PANE · Product context (hidden on mobile) ───────────────── --}}
        <aside class="relative hidden flex-col justify-between overflow-hidden p-10 lg:flex xl:p-14">
            {{-- Soft background accents --}}
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-[#1E3A8A]/25 blur-3xl"></div>
                <div class="absolute -bottom-40 -right-24 h-96 w-96 rounded-full bg-[#3B82F6]/15 blur-3xl"></div>
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(59,130,246,0.08),transparent_45%)]"></div>
            </div>

            <div class="relative">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/5 ring-1 ring-white/10">
                        <span class="text-[15px] font-bold tracking-tight text-white">OM</span>
                    </span>
                    <div class="leading-tight">
                        <p class="text-[15.5px] font-semibold tracking-tight text-white">OMET</p>
                        <p class="text-[11px] font-medium text-slate-400">Finance Management System</p>
                    </div>
                </div>
            </div>

            <div class="relative max-w-md">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-medium text-slate-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#60A5FA]"></span>
                    Enterprise Finance Platform
                </span>
                <h2 class="mt-5 text-[34px] font-semibold leading-[1.1] tracking-tight text-white xl:text-[40px]">
                    The single source of truth for <span class="text-[#60A5FA]">capital, projects,</span> and entities.
                </h2>
                <p class="mt-4 text-[14px] leading-relaxed text-slate-400">
                    OMET unifies project finance, multi-entity accounting, and treasury operations into one disciplined workspace — engineered for finance teams that need accuracy, traceability, and control at every layer of the organization.
                </p>

                <ul class="mt-8 space-y-3 text-[13px] text-slate-300">
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-white/5 ring-1 ring-white/10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[#60A5FA]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-7l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                        </span>
                        <div>
                            <p class="font-medium text-white">Project finance &amp; budget control</p>
                            <p class="text-[12px] text-slate-400">Contract values, collections, disbursements, and burn rate measured against board-approved budgets.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-white/5 ring-1 ring-white/10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[#60A5FA]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V10l7-5 7 5v11M9 21V13h6v8"/>
                            </svg>
                        </span>
                        <div>
                            <p class="font-medium text-white">Consolidated multi-entity ledgers</p>
                            <p class="text-[12px] text-slate-400">Real-time visibility into bank positions and cash movement across every subsidiary in the holding.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-white/5 ring-1 ring-white/10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[#60A5FA]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16l-4-4m0 0l4-4m-4 4h18m0 0l-4 4m4-4l-4-4"/>
                            </svg>
                        </span>
                        <div>
                            <p class="font-medium text-white">Treasury &amp; intercompany transfers</p>
                            <p class="text-[12px] text-slate-400">Move funds between accounts, projects, and entities with a complete, immutable audit trail.</p>
                        </div>
                    </li>
                </ul>
            </div>

            <p class="relative text-[11px] text-slate-500">© {{ now()->format('Y') }} OMET · Finance Management System</p>
        </aside>

        {{-- ── RIGHT PANE · Sign-in form ────────────────────────────────────── --}}
        <main class="flex min-h-screen items-center justify-center p-6 lg:bg-[#0F1E32] lg:p-10">
            <div class="w-full max-w-[400px]">

                {{-- Brand (mobile only — left pane carries it on desktop) --}}
                <div class="mb-6 flex items-center justify-center gap-3 lg:hidden">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/5 ring-1 ring-white/10">
                        <span class="text-[15px] font-bold tracking-tight text-white">OM</span>
                    </span>
                    <div class="leading-tight">
                        <p class="text-[15.5px] font-semibold tracking-tight text-white">OMET</p>
                        <p class="text-[11px] font-medium text-slate-400">Finance Management System</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/5 bg-white p-7 shadow-2xl shadow-black/40">

                    <div class="mb-6">
                        <h1 class="text-[18px] font-semibold tracking-tight text-slate-900">Sign in to OMET</h1>
                        <p class="mt-1 text-[13px] text-slate-500">Access your finance workspace with your authorized credentials.</p>
                    </div>

                    <x-auth-session-status class="mb-4" :status="session('status')" />

                    @if ($errors->any())
                    <div class="mb-4 flex gap-2 rounded-lg border border-red-100 bg-red-50 px-3 py-2.5 text-[12.5px] text-red-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="email" class="mb-1.5 block text-[12px] font-medium text-slate-600">Work email</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                class="block h-[42px] w-full rounded-lg border border-slate-200 bg-white px-3.5 text-[13.5px] text-slate-800 placeholder:text-slate-300 transition focus:border-[#3B82F6] focus:outline-none focus:ring-4 focus:ring-[#3B82F6]/10"
                                placeholder="you@omet.com"
                            />
                        </div>

                        <div x-data="{ show: false }">
                            <label for="password" class="mb-1.5 block text-[12px] font-medium text-slate-600">Password</label>
                            <div class="relative">
                                <input
                                    id="password"
                                    name="password"
                                    :type="show ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    class="block h-[42px] w-full rounded-lg border border-slate-200 bg-white px-3.5 pr-10 text-[13.5px] text-slate-800 placeholder:text-slate-300 transition focus:border-[#3B82F6] focus:outline-none focus:ring-4 focus:ring-[#3B82F6]/10"
                                    placeholder="••••••••"
                                />
                                <button type="button" @click="show = !show"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 transition hover:text-slate-700"
                                    :aria-label="show ? 'Hide password' : 'Show password'">
                                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908A3 3 0 1115 12m-6 0a3 3 0 013-3m6.062 6.062L3 3m6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <label class="flex cursor-pointer items-center gap-2 text-[12.5px] text-slate-500">
                                <input type="checkbox" name="remember" class="h-3.5 w-3.5 rounded border-slate-300 text-[#3B82F6] focus:ring-[#3B82F6]/30" />
                                Keep me signed in
                            </label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-[12.5px] font-medium text-[#3B82F6] hover:text-[#2563EB]">Forgot password?</a>
                            @endif
                        </div>

                        <button
                            type="submit"
                            class="mt-2 flex h-[42px] w-full items-center justify-center rounded-lg bg-[#0B1726] text-[13.5px] font-semibold text-white shadow-sm transition hover:bg-[#152439] focus:outline-none focus:ring-4 focus:ring-[#3B82F6]/20"
                        >
                            Sign in to OMET
                        </button>
                    </form>

                    <p class="mt-6 flex items-center justify-center gap-1.5 text-[11px] text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Encrypted session · restricted to authorized personnel
                    </p>
                </div>

                {{-- Mobile footer --}}
                <p class="mt-5 text-center text-[11px] text-slate-500 lg:hidden">
                    © {{ now()->format('Y') }} OMET · Finance Management System
                </p>

            </div>
        </main>

    </div>
</x-guest-layout>
