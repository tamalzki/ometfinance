# Mobile-Responsive Pass — Disbursements + Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Vouchers/Payables (Disbursements) and Reports modules usable on phones without changing any backend logic, Alpine.js state, or routes — pure Tailwind class edits on existing Blade markup.

**Architecture:** Three repeatable CSS recipes applied to existing files: (1) modal → full-screen sheet below `sm` via reshuffled Tailwind breakpoint prefixes, (2) toolbar inputs → full-width-stacked below `sm`, (3) sticky table "Actions" column → icon-only below `sm`. Every "always-on" class from the current desktop layout is preserved by moving it behind an `sm:` prefix, so desktop rendering is provably unchanged — only the unprefixed (mobile) state is new.

**Tech Stack:** Laravel 8.75 Blade views, Tailwind CSS (existing `tailwind.config.js`, no new config needed), Alpine.js (untouched — no `x-data` keys added/removed/renamed).

## Global Constraints

- No changes to PHP controllers, models, routes, migrations, or any `.php` file outside `resources/views`.
- No changes to Alpine.js `x-data` state shape, method names, or `@click`/`@submit` handlers — only the surrounding HTML/class attributes.
- Every desktop-visible class that exists today must still apply at `sm:` (640px) and above, unchanged in effect, so desktop UI is pixel-identical to before.
- Source of truth for this plan: `docs/superpowers/specs/2026-06-29-mobile-responsive-design.md`.
- This plan covers Phase 1 (reference example) + Phase 2 (Disbursements) + Phase 3 (Reports) only. Phase 4 (rest of app) gets its own plan after these phases are reviewed.

---

### Task 1: Payment modal → mobile sheet (reference example)

**Files:**
- Modify: `resources/views/vouchers/partials/payment-modal.blade.php:1-5`

**Interfaces:**
- Consumes: nothing new — `showPay`, `closePay()`, `payVoucher`, `p` Alpine state already exist in `vouchers/index.blade.php`'s `vouchersPage` component; this task does not touch them.
- Produces: the "modal sheet" class recipe — there is only one other form-style modal in scope for this plan, and it turned out to be dead code (see Task 2's note), so no other task reuses this recipe here. It remains the reference example for the Phase 4 plan's modals (Accounts, Projects, Transfers, Categories, etc.).

- [ ] **Step 1: Edit the modal wrapper and card classes**

Open `resources/views/vouchers/partials/payment-modal.blade.php`. Replace lines 1–6:

```html
{{-- RECORD PAYMENT modal — expects Alpine scope `vouchersPage` (p, payVoucher, showPay) --}}
<div x-cloak x-show="showPay"
     class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-6"
     @keydown.escape.window="closePay()">
    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl" @click.outside="closePay()">
        <form method="POST" enctype="multipart/form-data" class="flex max-h-[90vh] flex-col"
```

with:

```html
{{-- RECORD PAYMENT modal — expects Alpine scope `vouchersPage` (p, payVoucher, showPay) --}}
<div x-cloak x-show="showPay"
     class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 p-0 sm:px-4 sm:py-6"
     @keydown.escape.window="closePay()">
    <div class="flex h-full w-full flex-col bg-white shadow-2xl sm:h-auto sm:max-h-[90vh] sm:max-w-lg sm:rounded-2xl" @click.outside="closePay()">
        <form method="POST" enctype="multipart/form-data" class="flex h-full flex-col"
```

Leave every other line in the file untouched — the form body already has `class="flex-1 overflow-y-auto px-6 py-5 space-y-4"` on its scrollable region and a separate non-scrolling header/footer, which is exactly the structure a sheet needs (fixed header, scrollable middle, fixed footer).

- [ ] **Step 2: Visual check — desktop unchanged**

Use the `run` skill (or open `https://omet.test/vouchers` directly in a browser) at a desktop width (≥1024px). Click "Pay" on any open voucher row. Confirm the modal still appears as a centered card, max width ~32rem (`max-w-lg`), rounded corners, same as before this change. This must look pixel-identical to before the edit.

- [ ] **Step 3: Visual check — mobile sheet**

In the same browser, open devtools responsive mode at 375px width (iPhone SE) and reload `https://omet.test/vouchers`. Click "Pay" on a voucher row. Confirm:
- The modal now fills the entire viewport edge-to-edge (no visible black backdrop margin, no rounded corners).
- The header ("Record payment" + voucher number) stays visible at the top.
- The footer ("Cancel" / "Record payment" buttons) stays visible at the bottom.
- The middle section (bank account, date, amount, etc. fields) scrolls independently when content overflows.
- Tapping "Cancel" or the X closes the modal exactly as before (no JS was touched, so this should already work — confirm it still does).

- [ ] **Step 4: Commit**

```bash
git add resources/views/vouchers/partials/payment-modal.blade.php
git commit -m "$(cat <<'EOF'
Make payment modal a full-screen sheet on mobile

Desktop rendering is unchanged (all prior classes moved behind sm:);
below 640px the modal now fills the viewport with a fixed header/footer
and scrollable body, instead of a cramped centered box.
EOF
)"
```

---

### Task 2: Payables page → fluid toolbar + narrower sticky action column

**Note:** `resources/views/vouchers/partials/form-modal.blade.php` was investigated and found to be dead code — it is never `@include`d anywhere (`vouchers.create` and `vouchers.edit` routes render separate full-page views, not this modal). It is excluded from this plan; do not edit it.

Confirmed instead: `vouchers.payables` renders its own `resources/views/vouchers/payables.blade.php` (not `vouchers/index.blade.php`), with its own search box and its own sticky Action column — both need the same fixes as Task 3/4 apply to. It already `@include`s `vouchers/partials/payment-modal.blade.php`, so Task 1's sheet conversion already benefits this page with no extra work.

**Files:**
- Modify: `resources/views/vouchers/payables.blade.php:88-92` (search box)
- Modify: `resources/views/vouchers/payables.blade.php:144` (sticky Action header cell)
- Modify: `resources/views/vouchers/payables.blade.php:220-227` (sticky Action body cell + Pay button)

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new for later tasks.

- [ ] **Step 1: Make the search box full-width on mobile**

Replace:

```html
    <div class="relative">
        <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        <input type="search" x-model="q" autocomplete="off" placeholder="Search payee, voucher no., project…"
               class="h-8 w-64 rounded-md border border-slate-200 bg-white pl-8 pr-3 text-[12px] text-slate-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
    </div>
```

with:

```html
    <div class="relative w-full sm:w-auto">
        <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        <input type="search" x-model="q" autocomplete="off" placeholder="Search payee, voucher no., project…"
               class="h-8 w-full rounded-md border border-slate-200 bg-white pl-8 pr-3 text-[12px] text-slate-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-64">
    </div>
```

- [ ] **Step 2: Narrow the sticky Action header cell on mobile**

Replace:

```html
                <th class="sticky right-0 z-30 bg-slate-50 border-b border-slate-200 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[110px]">Action</th>
```

with:

```html
                <th class="sticky right-0 z-30 bg-slate-50 border-b border-slate-200 px-2 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 w-[64px] sm:w-[110px] sm:px-4">Action</th>
```

- [ ] **Step 3: Make the "Pay" button icon-only on mobile**

Replace:

```html
                    <td class="sticky right-0 z-10 border-b border-slate-100 bg-white px-3 py-2.5 align-middle group-hover:bg-slate-50" @click.stop>
                        <div class="flex items-center justify-end gap-1.5">
                            <button type="button" @click="openPay({{ \Illuminate\Support\Js::from($payload) }})"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100">
                                <i data-lucide="banknote" class="h-3 w-3 pointer-events-none"></i> Pay
                            </button>
                        </div>
                    </td>
```

with:

```html
                    <td class="sticky right-0 z-10 border-b border-slate-100 bg-white px-1.5 py-2.5 align-middle group-hover:bg-slate-50 sm:px-3" @click.stop>
                        <div class="flex items-center justify-end gap-1.5">
                            <button type="button" @click="openPay({{ \Illuminate\Support\Js::from($payload) }})"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-1 text-[11px] font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 sm:px-2.5">
                                <i data-lucide="banknote" class="h-3 w-3 pointer-events-none"></i><span class="hidden sm:inline"> Pay</span>
                            </button>
                        </div>
                    </td>
```

- [ ] **Step 4: Visual check — desktop unchanged**

At ≥1024px width, open `https://omet.test/vouchers/payables`. Confirm the search box, aging-bucket filter chips, and the table's Action column look identical to before — "Pay" button shows its text label, same widths.

- [ ] **Step 5: Visual check — mobile**

At 375px width, reload. Confirm: search box spans full width; aging-bucket chips (already `flex flex-wrap`) wrap cleanly with no overlap; scrolling the table horizontally reaches a narrower sticky Action column (~64px) showing only the banknote icon; tapping it still opens the same payment sheet from Task 1 (shared `payment-modal.blade.php`), pre-filled with that row's data exactly as before.

- [ ] **Step 6: Commit**

```bash
git add resources/views/vouchers/payables.blade.php
git commit -m "$(cat <<'EOF'
Make Payables search box fluid and Action column icon-only on mobile

Same recipes as the Vouchers index page: full-width search on mobile,
narrower sticky Action column with icon-only Pay button below 640px.
Desktop layout is unchanged. payment-modal.blade.php (already shared
with this page) was already converted to a mobile sheet in the
previous commit.
EOF
)"
```

---

### Task 3: Vouchers/Payables toolbar → fluid widths on mobile

**Files:**
- Modify: `resources/views/vouchers/index.blade.php:328-383`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new for later tasks. This view's toolbar serves both "Daily Transactions" (`vouchers.index`) and "Payables" (`vouchers.payables`, same Blade view, different controller filter) — fixing it here fixes both pages.

- [ ] **Step 1: Make the search box full-width on mobile**

Replace:

```html
    <div class="relative w-64">
        <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        <input type="search" x-model="q" autocomplete="off" placeholder="Search payee, number, project, category, source document"
               class="h-9 w-full rounded-md border border-slate-200 bg-white pl-8 pr-3 text-[12.5px] text-slate-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
    </div>
```

with:

```html
    <div class="relative w-full sm:w-64">
        <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        <input type="search" x-model="q" autocomplete="off" placeholder="Search payee, number, project, category, source document"
               class="h-9 w-full rounded-md border border-slate-200 bg-white pl-8 pr-3 text-[12.5px] text-slate-700 outline-none transition focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
    </div>
```

- [ ] **Step 2: Make the date-from/date-to pair stack cleanly on mobile**

Replace:

```html
        {{-- Date from --}}
        <div class="relative flex items-center">
            <i data-lucide="calendar" class="pointer-events-none absolute left-2.5 h-3.5 w-3.5 text-slate-400"></i>
            <input type="date" name="date_from" value="{{ $activeDateFrom }}"
                   onchange="this.form.submit()"
                   title="From date"
                   class="h-9 rounded-lg border border-slate-200 bg-white pl-8 pr-2 text-[12px] text-slate-700 shadow-sm outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 w-[140px]">
        </div>
        <span class="text-[11px] text-slate-400">to</span>
        <input type="date" name="date_to" value="{{ $activeDateTo }}"
               onchange="this.form.submit()"
               title="To date"
               class="h-9 rounded-lg border border-slate-200 bg-white px-2 text-[12px] text-slate-700 shadow-sm outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 w-[140px]">
```

with:

```html
        {{-- Date from/to --}}
        <div class="flex w-full items-center gap-2 sm:w-auto">
            <div class="relative flex flex-1 items-center sm:flex-none">
                <i data-lucide="calendar" class="pointer-events-none absolute left-2.5 h-3.5 w-3.5 text-slate-400"></i>
                <input type="date" name="date_from" value="{{ $activeDateFrom }}"
                       onchange="this.form.submit()"
                       title="From date"
                       class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-2 text-[12px] text-slate-700 shadow-sm outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-[140px]">
            </div>
            <span class="shrink-0 text-[11px] text-slate-400">to</span>
            <input type="date" name="date_to" value="{{ $activeDateTo }}"
                   onchange="this.form.submit()"
                   title="To date"
                   class="h-9 flex-1 rounded-lg border border-slate-200 bg-white px-2 text-[12px] text-slate-700 shadow-sm outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-[140px] sm:flex-none">
        </div>
```

- [ ] **Step 3: Make the Source and Status `<select>` filters full-width on mobile**

Replace:

```html
        {{-- Source filter --}}
        <div class="relative">
            <select name="source" onchange="this.form.submit()"
                    class="h-9 appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-[12px] text-slate-700 shadow-sm outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                <option value="">All sources</option>
                @foreach ($sources as $k => $label)
                    <option value="{{ $k }}" @selected($activeSource === $k)>{{ $label }}</option>
                @endforeach
            </select>
            <i data-lucide="chevron-down" class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        </div>

        {{-- Status filter --}}
        <div class="relative">
            <select name="status" onchange="this.form.submit()"
                    class="h-9 appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-[12px] text-slate-700 shadow-sm outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                <option value="">All statuses</option>
                @foreach ($statuses as $k => $label)
                    <option value="{{ $k }}" @selected($activeStatus === $k)>{{ $label }}</option>
                @endforeach
            </select>
            <i data-lucide="chevron-down" class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        </div>
```

with:

```html
        {{-- Source filter --}}
        <div class="relative w-full sm:w-auto">
            <select name="source" onchange="this.form.submit()"
                    class="h-9 w-full appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-[12px] text-slate-700 shadow-sm outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-auto">
                <option value="">All sources</option>
                @foreach ($sources as $k => $label)
                    <option value="{{ $k }}" @selected($activeSource === $k)>{{ $label }}</option>
                @endforeach
            </select>
            <i data-lucide="chevron-down" class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        </div>

        {{-- Status filter --}}
        <div class="relative w-full sm:w-auto">
            <select name="status" onchange="this.form.submit()"
                    class="h-9 w-full appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-[12px] text-slate-700 shadow-sm outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-auto">
                <option value="">All statuses</option>
                @foreach ($statuses as $k => $label)
                    <option value="{{ $k }}" @selected($activeStatus === $k)>{{ $label }}</option>
                @endforeach
            </select>
            <i data-lucide="chevron-down" class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"></i>
        </div>
```

- [ ] **Step 4: Visual check — desktop unchanged**

At ≥1024px width, open `https://omet.test/vouchers`. Confirm the toolbar row (search box, date pair, source/status dropdowns, clear-filters link) looks identical to before — same widths, same single-row layout.

- [ ] **Step 5: Visual check — mobile stacking**

At 375px width, reload the page. Confirm:
- Search box spans the full width.
- Date-from and date-to sit side by side on their own full-width row (each roughly half width), with "to" between them.
- Source filter spans full width on its own row.
- Status filter spans full width on its own row.
- No element is clipped, overlapping, or causes the page to scroll horizontally.
- Changing a date or dropdown still triggers the existing `onchange="this.form.submit()"` filter behavior (unchanged JS — just confirm it still submits).

- [ ] **Step 6: Commit**

```bash
git add resources/views/vouchers/index.blade.php
git commit -m "$(cat <<'EOF'
Stack Vouchers/Payables toolbar filters full-width on mobile

Search box, date range, and source/status filters were fixed-pixel
widths that crowded a flex-wrap row on narrow screens. They now stack
as clean full-width rows below 640px and are pixel-identical to the
existing desktop layout at sm: and above.
EOF
)"
```

---

### Task 4: Vouchers/Payables table — icon-only Actions column on mobile

**Files:**
- Modify: `resources/views/vouchers/index.blade.php:400` (header cell)
- Modify: `resources/views/vouchers/index.blade.php:534-609` (body action buttons)

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new for later tasks.

- [ ] **Step 1: Shrink the sticky Actions header cell on mobile**

Replace:

```html
                <th class="sticky right-0 z-30 border-b border-l border-slate-200 bg-slate-50 px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 min-w-[15rem]">Actions</th>
```

with:

```html
                <th class="sticky right-0 z-30 border-b border-l border-slate-200 bg-slate-50 px-2 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500 min-w-[104px] sm:min-w-[15rem] sm:px-4">Actions</th>
```

- [ ] **Step 2: Shrink the sticky Actions body cell and button row gap**

Replace:

```html
                    <td class="sticky right-0 z-10 border-b border-l border-slate-200 bg-white px-3 py-2.5 align-middle group-hover:bg-slate-50" @click.stop>
                        <div class="flex flex-row flex-nowrap items-center justify-end gap-1.5">
```

with:

```html
                    <td class="sticky right-0 z-10 border-b border-l border-slate-200 bg-white px-1.5 py-2.5 align-middle group-hover:bg-slate-50 sm:px-3" @click.stop>
                        <div class="flex flex-row flex-nowrap items-center justify-end gap-1 sm:gap-1.5">
```

- [ ] **Step 3: Make the "Pay" button icon-only on mobile**

Replace:

```html
                                <button type="button"
                                        @if ($payLocked) disabled title="{{ $payLockReason }}"
                                        @else @click="openPay({{ \Illuminate\Support\Js::from($payload) }})" @endif
                                        @class([
                                            'inline-flex shrink-0 items-center gap-1 rounded-md border px-2.5 py-1 text-[11px] font-semibold shadow-sm transition',
                                            'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' => ! $payLocked,
                                            'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400' => $payLocked,
                                        ])>
                                    <i data-lucide="banknote" class="h-3 w-3 pointer-events-none"></i> Pay
                                </button>
```

with:

```html
                                <button type="button"
                                        @if ($payLocked) disabled title="{{ $payLockReason }}"
                                        @else @click="openPay({{ \Illuminate\Support\Js::from($payload) }})" @endif
                                        @class([
                                            'inline-flex shrink-0 items-center gap-1 rounded-md border px-1.5 py-1 text-[11px] font-semibold shadow-sm transition sm:px-2.5',
                                            'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' => ! $payLocked,
                                            'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400' => $payLocked,
                                        ])>
                                    <i data-lucide="banknote" class="h-3 w-3 pointer-events-none"></i><span class="hidden sm:inline"> Pay</span>
                                </button>
```

- [ ] **Step 4: Make the "Edit" link and its locked placeholder icon-only on mobile**

Replace:

```html
                                <span title="{{ $lockReason }}"
                                      class="inline-flex shrink-0 cursor-not-allowed items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-400">
                                    <i data-lucide="pencil" class="h-3 w-3 pointer-events-none"></i> Edit
                                </span>
                            @else
                                <a href="{{ route('vouchers.edit', $v) }}" @click.stop
                                   class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                                    <i data-lucide="pencil" class="h-3 w-3 pointer-events-none"></i> Edit
                                </a>
```

with:

```html
                                <span title="{{ $lockReason }}"
                                      class="inline-flex shrink-0 cursor-not-allowed items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-1.5 py-1 text-[11px] font-semibold text-slate-400 sm:px-2.5">
                                    <i data-lucide="pencil" class="h-3 w-3 pointer-events-none"></i><span class="hidden sm:inline"> Edit</span>
                                </span>
                            @else
                                <a href="{{ route('vouchers.edit', $v) }}" @click.stop
                                   class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-200 bg-white px-1.5 py-1 text-[11px] font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50 sm:px-2.5">
                                    <i data-lucide="pencil" class="h-3 w-3 pointer-events-none"></i><span class="hidden sm:inline"> Edit</span>
                                </a>
```

- [ ] **Step 5: Make the "Cancel" and "Reactivate" buttons icon-only on mobile**

Replace:

```html
                            @if ($v->isOpen() && $v->payments->isEmpty())
                                <form method="POST" action="{{ route('vouchers.cancel', $v->id) }}"
                                      onsubmit="return confirm('Cancel voucher {{ $v->voucher_no }}? It will be excluded from payables and can be reactivated later.');" class="inline-flex shrink-0">
                                    @csrf
                                    <button type="submit" @if ($notYetApproved) disabled title="{{ $lockReason }}" @endif
                                            @class([
                                                'inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-[11px] font-semibold shadow-sm transition',
                                                'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100' => ! $notYetApproved,
                                                'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400' => $notYetApproved,
                                            ])>
                                        <i data-lucide="ban" class="h-3 w-3 pointer-events-none"></i> Cancel
                                    </button>
                                </form>
                            @elseif ($v->status === 'cancelled')
                                <form method="POST" action="{{ route('vouchers.reactivate', $v->id) }}" class="inline-flex shrink-0">
                                    @csrf
                                    <button type="submit" @if ($notYetApproved) disabled title="{{ $lockReason }}" @endif
                                            @class([
                                                'inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-[11px] font-semibold shadow-sm transition',
                                                'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' => ! $notYetApproved,
                                                'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400' => $notYetApproved,
                                            ])>
                                        <i data-lucide="rotate-ccw" class="h-3 w-3 pointer-events-none"></i> Reactivate
                                    </button>
                                </form>
                            @endif
```

with:

```html
                            @if ($v->isOpen() && $v->payments->isEmpty())
                                <form method="POST" action="{{ route('vouchers.cancel', $v->id) }}"
                                      onsubmit="return confirm('Cancel voucher {{ $v->voucher_no }}? It will be excluded from payables and can be reactivated later.');" class="inline-flex shrink-0">
                                    @csrf
                                    <button type="submit" @if ($notYetApproved) disabled title="{{ $lockReason }}" @endif
                                            @class([
                                                'inline-flex items-center gap-1 rounded-md border px-1.5 py-1 text-[11px] font-semibold shadow-sm transition sm:px-2.5',
                                                'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100' => ! $notYetApproved,
                                                'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400' => $notYetApproved,
                                            ])>
                                        <i data-lucide="ban" class="h-3 w-3 pointer-events-none"></i><span class="hidden sm:inline"> Cancel</span>
                                    </button>
                                </form>
                            @elseif ($v->status === 'cancelled')
                                <form method="POST" action="{{ route('vouchers.reactivate', $v->id) }}" class="inline-flex shrink-0">
                                    @csrf
                                    <button type="submit" @if ($notYetApproved) disabled title="{{ $lockReason }}" @endif
                                            @class([
                                                'inline-flex items-center gap-1 rounded-md border px-1.5 py-1 text-[11px] font-semibold shadow-sm transition sm:px-2.5',
                                                'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' => ! $notYetApproved,
                                                'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400' => $notYetApproved,
                                            ])>
                                        <i data-lucide="rotate-ccw" class="h-3 w-3 pointer-events-none"></i><span class="hidden sm:inline"> Reactivate</span>
                                    </button>
                                </form>
                            @endif
```

Note: the Delete button and its locked placeholder span are already icon-only (no text label) in the current code — leave both completely untouched.

- [ ] **Step 6: Visual check — desktop unchanged**

At ≥1024px width, open `https://omet.test/vouchers`. Confirm every action button still shows its icon + text label exactly as before, same spacing, same sticky column width.

- [ ] **Step 7: Visual check — mobile icon-only column**

At 375px width, reload. Scroll the table horizontally to reach the Actions column. Confirm:
- Action buttons show icons only (no "Pay"/"Edit"/"Cancel"/"Reactivate" text), each still tappable.
- The sticky column is noticeably narrower than before (roughly 100px instead of 240px), leaving more room for the scrollable content to its left.
- Hovering/tapping still triggers the same confirm dialogs and form submissions as before (unchanged handlers — just confirm they still fire, e.g. the "Cancel" confirm() prompt still appears).
- No two buttons overlap each other or get clipped by the sticky column edge.

- [ ] **Step 8: Commit**

```bash
git add resources/views/vouchers/index.blade.php
git commit -m "$(cat <<'EOF'
Shrink Vouchers/Payables sticky Actions column to icon-only on mobile

The pinned Actions column was ~240px wide with text-labeled buttons,
which dominated the viewport on phones when scrolling the table
horizontally. Below 640px it now shows icons only (~104px wide);
text labels return unchanged at sm: and above. No button handlers,
confirm() prompts, or routes were touched.
EOF
)"
```

---

### Task 5: Reports filter bar → fluid widths on mobile

**Files:**
- Modify: `resources/views/reports/index.blade.php:78-193`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new for later tasks. This filter bar is shared across all report tabs except "Overall Position" (which has no filter form).

- [ ] **Step 1: Stack the filter form vertically on mobile, row on desktop**

Replace the form's opening tag:

```html
        <form method="GET" action="{{ $reportRouteUrl }}"
              class="no-print flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2.5">
```

with:

```html
        <form method="GET" action="{{ $reportRouteUrl }}"
              class="no-print flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2.5 sm:flex-row sm:flex-wrap sm:items-end">
```

- [ ] **Step 2: Make the From/To date fields full-width on mobile**

Replace:

```html
            <div>
                <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">From</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                       class="mt-1 h-9 min-w-[8.5rem] rounded-md border border-slate-200 bg-white px-3 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
            </div>
            <div>
                <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">To</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                       class="mt-1 h-9 min-w-[8.5rem] rounded-md border border-slate-200 bg-white px-3 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
            </div>
```

with:

```html
            <div class="w-full sm:w-auto">
                <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">From</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                       class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-auto sm:min-w-[8.5rem]">
            </div>
            <div class="w-full sm:w-auto">
                <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">To</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                       class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-auto sm:min-w-[8.5rem]">
            </div>
```

- [ ] **Step 3: Make the Project filter full-width on mobile**

Replace:

```html
            @if (in_array($activeTab, ['cash-outflow', 'collections', 'payables', 'vouchers'], true))
                <div>
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Project</label>
                    <select name="project_id"
                            class="mt-1 h-9 min-w-[12rem] rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                        <option value="">All projects</option>
                        @foreach ($projectsForFilter as $p)
                            <option value="{{ $p->id }}" {{ (string) $filters['project_id'] === (string) $p->id ? 'selected' : '' }}>
                                {{ $p->name }}{{ $p->kind === 'in_house' ? ' (in-house)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
```

with:

```html
            @if (in_array($activeTab, ['cash-outflow', 'collections', 'payables', 'vouchers'], true))
                <div class="w-full sm:w-auto">
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Project</label>
                    <select name="project_id"
                            class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-auto sm:min-w-[12rem]">
                        <option value="">All projects</option>
                        @foreach ($projectsForFilter as $p)
                            <option value="{{ $p->id }}" {{ (string) $filters['project_id'] === (string) $p->id ? 'selected' : '' }}>
                                {{ $p->name }}{{ $p->kind === 'in_house' ? ' (in-house)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
```

- [ ] **Step 4: Make the Voucher Register filters (Source/Status/Type) full-width on mobile**

Replace:

```html
            @if ($activeTab === 'vouchers')
                <div>
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Source</label>
                    <select name="source"
                            class="mt-1 h-9 min-w-[9rem] rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                        <option value="">All sources</option>
                        @foreach ($sourcesForFilter as $key => $label)
                            <option value="{{ $key }}" {{ $filters['source'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Status</label>
                    <select name="status"
                            class="mt-1 h-9 min-w-[10rem] rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                        <option value="">All statuses</option>
                        @foreach ($statusesForFilter as $key => $label)
                            <option value="{{ $key }}" {{ $filters['status'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Type</label>
                    <select name="transaction_type"
                            class="mt-1 h-9 min-w-[11rem] rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                        <option value="">All types</option>
                        @foreach ($typesForFilter as $key => $label)
                            <option value="{{ $key }}" {{ $filters['transaction_type'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
```

with:

```html
            @if ($activeTab === 'vouchers')
                <div class="w-full sm:w-auto">
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Source</label>
                    <select name="source"
                            class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-auto sm:min-w-[9rem]">
                        <option value="">All sources</option>
                        @foreach ($sourcesForFilter as $key => $label)
                            <option value="{{ $key }}" {{ $filters['source'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Status</label>
                    <select name="status"
                            class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-auto sm:min-w-[10rem]">
                        <option value="">All statuses</option>
                        @foreach ($statusesForFilter as $key => $label)
                            <option value="{{ $key }}" {{ $filters['status'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Type</label>
                    <select name="transaction_type"
                            class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-auto sm:min-w-[11rem]">
                        <option value="">All types</option>
                        @foreach ($typesForFilter as $key => $label)
                            <option value="{{ $key }}" {{ $filters['transaction_type'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
```

- [ ] **Step 5: Make the Entity, Category, and Account filters full-width on mobile**

Replace:

```html
            @if (in_array($activeTab, ['cash-outflow', 'account-balances', 'transfers'], true))
                <div>
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Entity</label>
                    <select name="entity"
                            class="mt-1 h-9 min-w-[10rem] rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                        <option value="">All entities</option>
                        @foreach ($entities as $e)
                            <option value="{{ $e->slug }}" {{ $filters['entity'] === $e->slug ? 'selected' : '' }}>{{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($activeTab === 'cash-outflow')
                <div>
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Category</label>
                    <select name="category_id"
                            class="mt-1 h-9 min-w-[12rem] rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                        <option value="">All categories</option>
                        @foreach ($categoriesForFilter as $c)
                            <option value="{{ $c['id'] }}" {{ (string) $filters['category_id'] === (string) $c['id'] ? 'selected' : '' }}>
                                {{ $c['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($activeTab === 'transfers')
                <div>
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Account</label>
                    <select name="account_id"
                            class="mt-1 h-9 min-w-[14rem] rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15">
                        <option value="">All accounts</option>
                        @foreach ($accountsForFilter as $a)
                            <option value="{{ $a->id }}" {{ (string) $filters['account_id'] === (string) $a->id ? 'selected' : '' }}>
                                {{ $a->entity?->name }} — {{ $a->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
```

with:

```html
            @if (in_array($activeTab, ['cash-outflow', 'account-balances', 'transfers'], true))
                <div class="w-full sm:w-auto">
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Entity</label>
                    <select name="entity"
                            class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-auto sm:min-w-[10rem]">
                        <option value="">All entities</option>
                        @foreach ($entities as $e)
                            <option value="{{ $e->slug }}" {{ $filters['entity'] === $e->slug ? 'selected' : '' }}>{{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($activeTab === 'cash-outflow')
                <div class="w-full sm:w-auto">
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Category</label>
                    <select name="category_id"
                            class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-auto sm:min-w-[12rem]">
                        <option value="">All categories</option>
                        @foreach ($categoriesForFilter as $c)
                            <option value="{{ $c['id'] }}" {{ (string) $filters['category_id'] === (string) $c['id'] ? 'selected' : '' }}>
                                {{ $c['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($activeTab === 'transfers')
                <div class="w-full sm:w-auto">
                    <label class="block text-[10.5px] font-semibold uppercase tracking-wider text-slate-500">Account</label>
                    <select name="account_id"
                            class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-2 text-[12.5px] text-slate-700 outline-none focus:border-omet-blue focus:ring-2 focus:ring-omet-blue/15 sm:w-auto sm:min-w-[14rem]">
                        <option value="">All accounts</option>
                        @foreach ($accountsForFilter as $a)
                            <option value="{{ $a->id }}" {{ (string) $filters['account_id'] === (string) $a->id ? 'selected' : '' }}>
                                {{ $a->entity?->name }} — {{ $a->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
```

- [ ] **Step 6: Make the Generate/Reset button row full-width and split evenly on mobile**

Replace:

```html
            <div class="flex gap-2">
                <button type="submit"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md bg-omet-blue px-3.5 text-[12.5px] font-semibold text-white shadow-sm hover:bg-omet-lightblue">
                    <i data-lucide="play" class="h-3.5 w-3.5"></i>
                    Generate
                </button>
                <a href="{{ $reportRouteUrl }}"
                   class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-[12.5px] font-medium text-slate-600 hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </form>
```

with:

```html
            <div class="flex w-full gap-2 sm:w-auto">
                <button type="submit"
                        class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-md bg-omet-blue px-3.5 text-[12.5px] font-semibold text-white shadow-sm hover:bg-omet-lightblue sm:flex-none">
                    <i data-lucide="play" class="h-3.5 w-3.5"></i>
                    Generate
                </button>
                <a href="{{ $reportRouteUrl }}"
                   class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-[12.5px] font-medium text-slate-600 hover:bg-slate-50 sm:flex-none">
                    Reset
                </a>
            </div>
        </form>
```

- [ ] **Step 7: Visual check — desktop unchanged**

At ≥1024px width, visit each report tab that has a filter bar (`https://omet.test/reports/cash-outflow`, `/reports/account-balances`, `/reports/transfers`, `/reports/collections`, `/reports/payables`, `/reports/vouchers`). Confirm each filter bar still renders as a single horizontal row, identical widths to before.

- [ ] **Step 8: Visual check — mobile stacking**

At 375px width, revisit the same tabs. Confirm:
- Every filter field spans the full row width, stacked vertically with its label above it.
- The Generate/Reset buttons sit side by side, each taking half the row width.
- No filter field is clipped or overlaps another.
- Submitting the form (tap "Generate") still reloads the page with the selected filters applied (unchanged form `method="GET"` — just confirm the query string still reflects your selections).
- The tab nav bar above the filters and the table below (already wrapped in `overflow-x-auto`) are unaffected.

- [ ] **Step 9: Commit**

```bash
git add resources/views/reports/index.blade.php
git commit -m "$(cat <<'EOF'
Stack Reports filter bar full-width on mobile

Date range, project/source/status/type/entity/category/account filters
were fixed min-width selects crowding one flex-wrap row. Below 640px
they now stack as full-width labeled rows with Generate/Reset split
evenly; desktop layout at sm: and above is unchanged.
EOF
)"
```

---

### Task 6: Phase 2+3 end-to-end review checkpoint

**Files:** none (verification only).

**Interfaces:** none.

- [ ] **Step 1: Full walkthrough at 375px, 390px, 768px, and 1024px+**

For each of the following pages, load at each of the four widths above and confirm: no horizontal page-level scrollbar, no two interactive elements visually overlapping, all text readable without truncation surprises, and every interactive element (button, link, filter, modal trigger) remains tappable/clickable:

- `https://omet.test/vouchers` (Daily Transactions)
- `https://omet.test/vouchers/payables` (Payables — same view, confirms Task 3/4 fixes apply here too)
- `https://omet.test/voucher-requests` (Voucher Approvals / Payment Verification — confirm this page was unaffected by changes elsewhere, since it wasn't edited in this plan)
- `https://omet.test/reports`, `/reports/cash-outflow`, `/reports/collections`, `/reports/payables`, `/reports/vouchers` (and `/reports/account-balances`, `/reports/transfers` if logged in as admin)

- [ ] **Step 2: Confirm no regressions in existing functionality**

Perform one real action per priority module to confirm nothing broke: record a payment on an open voucher (Pay modal), edit a voucher (Edit modal), and generate a filtered report export. All three should behave exactly as before — only their visual presentation at narrow widths changed.

- [ ] **Step 3: Report back for checkpoint review**

Summarize what was changed (the 5 files touched: `vouchers/partials/payment-modal.blade.php`, `vouchers/payables.blade.php`, `vouchers/index.blade.php`, `reports/index.blade.php`, plus the note that `vouchers/partials/form-modal.blade.php` was found to be dead code and intentionally left untouched) and flag anything observed during the walkthrough that looks off, before starting Phase 4 (rest of the app) as a separate plan.
