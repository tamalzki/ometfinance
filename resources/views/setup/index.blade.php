<x-guest-layout>
@php
    $roleLabel = match ($invite->role) {
        'cfo'        => 'CFO',
        'accounting' => 'Accounting',
        default      => 'administrator',
    };
    $officeLabel = ($invite->role === 'accounting' && ($invite->source ?? null))
        ? ($invite->source === 'bgc' ? 'BGC' : 'Main')
        : null;
@endphp
    <div class="min-h-screen bg-[#0B1726] lg:grid lg:grid-cols-[1.1fr_1fr]">

        {{-- ── LEFT PANE · Product context ───────────────────────────────────── --}}
        <aside class="relative hidden flex-col justify-between overflow-hidden p-10 lg:flex xl:p-14">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-[#1E3A8A]/25 blur-3xl"></div>
                <div class="absolute -bottom-40 -right-24 h-96 w-96 rounded-full bg-[#3B82F6]/15 blur-3xl"></div>
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(59,130,246,0.08),transparent_45%)]"></div>
            </div>

            <div class="relative">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/5 ring-1 ring-white/10">
                        <span class="text-[17px] font-bold tracking-tight text-white">O</span>
                    </span>
                    <div class="leading-tight">
                        <p class="text-[15.5px] font-semibold tracking-tight text-white">OMET</p>
                        <p class="text-[11px] font-medium text-slate-400">Finance Management System</p>
                    </div>
                </div>
            </div>

            <div class="relative max-w-md">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-medium uppercase tracking-wide text-[#60A5FA]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    One-time account setup
                </span>
                <h2 class="mt-4 text-[34px] font-semibold leading-[1.1] tracking-tight text-white xl:text-[40px]">
                    Finish setting up your <span class="text-[#60A5FA]">{{ $roleLabel }}</span> account.
                </h2>
                <p class="mt-4 text-[14px] leading-relaxed text-slate-400">
                    This invite was issued from the OMET CLI and is tied to a single email.
                    Once used, the link is permanently invalidated.
                    @if ($officeLabel)
                        Your vouchers will be locked to the <span class="font-medium text-slate-300">{{ $officeLabel }}</span> office.
                    @endif
                </p>

                <ul class="mt-8 space-y-3 text-[13px] text-slate-300">
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-white/5 ring-1 ring-white/10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[#60A5FA]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <div>
                            <p class="font-medium text-white">Token-gated access</p>
                            <p class="text-[12px] text-slate-400">Only someone with the exact 64-character invite token can reach this page.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-white/5 ring-1 ring-white/10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[#60A5FA]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <div>
                            <p class="font-medium text-white">Expires in 24 hours</p>
                            <p class="text-[12px] text-slate-400">After the window closes the link cannot be reused or revived.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-white/5 ring-1 ring-white/10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-[#60A5FA]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span>
                        <div>
                            <p class="font-medium text-white">Single-use</p>
                            <p class="text-[12px] text-slate-400">The token is consumed the moment the account is created.</p>
                        </div>
                    </li>
                </ul>
            </div>

            <p class="relative text-[11px] text-slate-500">© {{ now()->format('Y') }} OMET · Finance Management System</p>
        </aside>

        {{-- ── RIGHT PANE · Setup form ───────────────────────────────────────── --}}
        <main class="flex min-h-screen items-center justify-center p-6 lg:bg-[#0F1E32] lg:p-10">
            <div class="w-full max-w-[440px]">

                {{-- Mobile brand --}}
                <div class="mb-6 flex items-center justify-center gap-3 lg:hidden">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/5 ring-1 ring-white/10">
                        <span class="text-[17px] font-bold tracking-tight text-white">O</span>
                    </span>
                    <div class="leading-tight">
                        <p class="text-[15.5px] font-semibold tracking-tight text-white">OMET</p>
                        <p class="text-[11px] font-medium text-slate-400">Finance Management System</p>
                    </div>
                </div>

                <div
                    x-data="{
                        expiresAt: new Date('{{ \Illuminate\Support\Carbon::parse($invite->expires_at)->toIso8601String() }}').getTime(),
                        remaining: '',
                        expired: false,
                        tick() {
                            const ms = this.expiresAt - Date.now();
                            if (ms <= 0) { this.remaining = '00:00:00'; this.expired = true; return; }
                            const h = Math.floor(ms / 3600000);
                            const m = Math.floor((ms % 3600000) / 60000);
                            const s = Math.floor((ms % 60000) / 1000);
                            const pad = (n) => String(n).padStart(2, '0');
                            this.remaining = pad(h) + ':' + pad(m) + ':' + pad(s);
                        }
                    }"
                    x-init="tick(); setInterval(() => tick(), 1000)"
                    class="rounded-2xl border border-white/5 bg-white p-7 shadow-2xl shadow-black/40"
                >

                    <div class="mb-5">
                        <h1 class="text-[18px] font-semibold tracking-tight text-slate-900">Set up your {{ $roleLabel }} account</h1>
                        <p class="mt-1 text-[13px] text-slate-500">
                            Inviting <span class="font-medium text-slate-700">{{ $invite->email }}</span>
                        </p>
                    </div>

                    {{-- Security / expiry banner --}}
                    <div class="mb-5 flex items-center gap-3 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="flex-1 text-[12px] leading-tight text-amber-800">
                            <p class="font-medium">Secure, single-use link</p>
                            <p class="text-amber-700/90">
                                Expires in <span class="font-semibold tabular-nums" x-text="remaining">--:--:--</span>
                                · {{ \Illuminate\Support\Carbon::parse($invite->expires_at)->format('M j, Y g:i A') }}
                            </p>
                        </div>
                    </div>

                    @if ($errors->any())
                    <div class="mb-4 flex gap-2 rounded-lg border border-red-100 bg-red-50 px-3 py-2.5 text-[12.5px] text-red-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        <ul class="space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <form method="POST" action="{{ url('/setup') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">

                        <div>
                            <label for="email" class="mb-1.5 block text-[12px] font-medium text-slate-600">Email</label>
                            <input
                                id="email"
                                type="email"
                                value="{{ $invite->email }}"
                                disabled
                                class="block h-[42px] w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3.5 text-[13.5px] text-slate-500"
                            />
                        </div>

                        <div>
                            <label for="name" class="mb-1.5 block text-[12px] font-medium text-slate-600">Full name</label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                value="{{ old('name') }}"
                                required
                                autofocus
                                autocomplete="name"
                                class="block h-[42px] w-full rounded-lg border border-slate-200 bg-white px-3.5 text-[13.5px] text-slate-800 placeholder:text-slate-300 transition focus:border-[#3B82F6] focus:outline-none focus:ring-4 focus:ring-[#3B82F6]/10"
                                placeholder="Your name"
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
                                    autocomplete="new-password"
                                    minlength="8"
                                    class="block h-[42px] w-full rounded-lg border border-slate-200 bg-white px-3.5 pr-10 text-[13.5px] text-slate-800 placeholder:text-slate-300 transition focus:border-[#3B82F6] focus:outline-none focus:ring-4 focus:ring-[#3B82F6]/10"
                                    placeholder="At least 8 characters"
                                />
                                <button type="button" @click="show = !show"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 transition hover:text-slate-700"
                                    :aria-label="show ? 'Hide password' : 'Show password'">
                                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908A3 3 0 1115 12m-6 0a3 3 0 013-3m6.062 6.062L3 3m6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-1.5 block text-[12px] font-medium text-slate-600">Confirm password</label>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                minlength="8"
                                class="block h-[42px] w-full rounded-lg border border-slate-200 bg-white px-3.5 text-[13.5px] text-slate-800 placeholder:text-slate-300 transition focus:border-[#3B82F6] focus:outline-none focus:ring-4 focus:ring-[#3B82F6]/10"
                                placeholder="Re-enter password"
                            />
                        </div>

                        <button
                            type="submit"
                            :disabled="expired"
                            class="mt-2 flex h-[42px] w-full items-center justify-center rounded-lg bg-[#0B1726] text-[13.5px] font-semibold text-white shadow-sm transition hover:bg-[#152439] focus:outline-none focus:ring-4 focus:ring-[#3B82F6]/20 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span x-show="!expired">Create {{ $roleLabel }} account</span>
                            <span x-show="expired" x-cloak>Invite expired</span>
                        </button>
                    </form>

                    <p class="mt-5 flex items-center justify-center gap-1.5 text-[11px] text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Token-gated · single-use · expires in 24 hours
                    </p>
                </div>

                <p class="mt-5 text-center text-[11px] text-slate-500 lg:hidden">
                    © {{ now()->format('Y') }} OMET · Finance Management System
                </p>

            </div>
        </main>

    </div>
</x-guest-layout>
