# Mobile-Responsive Pass — Design

## Goal

Make OMET usable on phones/tablets without sacrificing the current desktop UI, without overlapping/cramped elements, and **without touching any backend logic, routes, migrations, or Alpine.js state**. This is a CSS/Tailwind-class-only effort layered on existing markup.

## Context

- Laravel 8.75 + Blade + Alpine.js + Tailwind. 70 Blade views; several pages run 500–950 lines.
- The app shell (`layouts/app.blade.php`, `components/sidebar.blade.php`, `components/header.blade.php`) already has working mobile scaffolding: sidebar slides in/out via `sidebarOpen` Alpine state, hamburger button shows below `lg`, overlay backdrop on mobile. **No changes needed here**, just verification.
- Data tables (`vouchers/index`, `reports/index`, `projects/index`, `accounts/index`, `transfers/index`) are already wrapped in `.data-grid` containers with `overflow-auto`/`overflow-x-auto` — horizontal scroll already works. Per product decision, we keep this pattern rather than converting to stacked mobile cards.
- KPI/summary grids mostly already use responsive classes (`grid-cols-1 sm:grid-cols-2 ...`, `grid-cols-2 lg:grid-cols-4`) — Dashboard and Reports "Overall Position" need no changes.
- Detail pages (`vouchers/show`, `vouchers/requests/show`) are single-column flow layouts already — they stack fine on narrow viewports as-is.

## What's actually broken on mobile

1. **Fixed-pixel toolbar inputs.** Search boxes (`w-64`), date filters (`w-[140px]` ×2), and several filter `<select>`s (`min-w-[8.5rem]` to `min-w-[14rem]`) don't shrink. In a `flex flex-wrap` toolbar this causes cramped/overlapping rows at ~320–375px viewport widths.
2. **Sticky table "Actions" column.** Vouchers/Payables table has a `sticky right-0` column with `min-w-[15rem]` (240px) holding 3–4 text+icon buttons (Pay/Edit/Cancel/Delete). On a 375px-wide phone this single pinned column eats ~64% of the viewport, leaving almost nothing for the scrollable content and making the horizontal-scroll pattern nearly useless.
3. **Modals are fixed centered boxes.** ~9 modal partials across the app share one pattern: `fixed inset-0 ... flex items-center justify-center bg-black/40 px-4 py-6` wrapping a `w-full max-w-lg rounded-2xl` card with internal `max-h-[90vh]` scroll. On a phone, the `px-4 py-6` outer padding plus centered card wastes screen space and the keyboard (for inputs) competes with the scrollable area.

## Recipes (apply repeatedly, no JS changes)

### 1. Modal → mobile sheet
- Outer wrapper: `px-4 py-6` → `p-0 sm:px-4 sm:py-6`
- Inner card: `w-full max-w-lg rounded-2xl` → `flex h-full w-full flex-col sm:h-auto sm:max-h-[90vh] sm:max-w-lg sm:rounded-2xl`
- Inner scrollable form/body keeps `flex-1 overflow-y-auto`; header/footer stay non-scrolling (already `shrink-0`-equivalent via being outside the scroll div).
- Result: below `sm` (640px) the modal fills the viewport edge-to-edge, scrolls internally between a fixed header and fixed footer (so action buttons stay reachable above the keyboard). At `sm`+, identical to today's centered card.
- Files: `vouchers/partials/payment-modal.blade.php`, `vouchers/partials/form-modal.blade.php`, `vouchers/show.blade.php`, `vouchers/requests/show.blade.php`, `accounts/index.blade.php`, `accounts/overall.blade.php`, `categories/index.blade.php`, `projects/index.blade.php`, `projects/external/allocation.blade.php`, `transfers/index.blade.php`, `components/project-shell.blade.php`.

### 2. Toolbar inputs → fluid width
- `w-64` → `w-full sm:w-64`
- `w-[140px]` → `w-full sm:w-[140px]` (paired date-from/date-to inputs each get their own full row on mobile, sit side-by-side at `sm`+)
- `min-w-[Nrem]` filter selects → add `w-full sm:w-auto` so they don't force a wide minimum on a narrow flex-wrap row.
- No change to `name`/`onchange`/form submission behavior — purely sizing.

### 3. Sticky Actions column → icon-only on mobile
- Column header/cell width: `min-w-[15rem]` → `min-w-[64px] sm:min-w-[15rem]`
- Each action button's text label wrapped: `<span class="hidden sm:inline">Pay</span>` (icon stays visible always); button padding tightens on mobile (`px-2 sm:px-2.5`).
- No handlers, routes, or confirm()/prompt() logic touched — only label visibility and spacing.

### 4. General sweep / verification
- After each phase, check for introduced horizontal page overflow (`overflow-x-hidden` on `body`/`main` should never be needed if widths are fluid — if it is needed somewhere, that's a sign a fixed-width element was missed).
- Confirm existing responsive grids (Dashboard, Reports KPIs, summary cards) need no changes — verify visually, don't touch unless actually broken.

## Phasing (review checkpoint after each)

1. **Phase 1 — Shared/global verification.** Confirm sidebar/header mobile behavior still correct (no regressions expected, no changes planned). Implement and verify the modal-sheet recipe on `payment-modal.blade.php` first as the reference example.
2. **Phase 2 — Disbursements (priority module).** Apply all three recipes to: `vouchers/index.blade.php` (covers both Daily Transactions and Payables — same view, different controller filter), `vouchers/partials/payment-modal.blade.php`, `vouchers/partials/form-modal.blade.php`, `vouchers/show.blade.php`, `vouchers/requests/show.blade.php` (Voucher Approvals + Payment Verification).
3. **Phase 3 — Reports (priority module).** Apply toolbar-fluid-width recipe to all 6 report tabs' filter bar (`reports/index.blade.php`); confirm export/print button row and tab nav need no changes (already responsive); confirm tables' existing `overflow-x-auto` is sufficient.
4. **Phase 4 — Rest of app.** Apply the same three recipes where applicable to Accounts, Projects, Transfers, Categories, Dashboard, Settings, Payroll, Budget, Invoices, Purchase Orders — lower priority, same recipes, no new patterns expected.

## Explicitly out of scope

- No backend/controller/route/migration changes.
- No Alpine.js state, event handler, or business-logic changes.
- No conversion of tables to stacked mobile cards (decided: keep horizontal scroll).
- No new shared Blade components/partials (avoids restructuring 9+ files just to dedupe markup — direct class edits are lower-risk for a no-logic-change constraint).
- No changes to pages/areas not listed above unless Phase 4 review surfaces something broken.

## Testing approach

Manual verification in browser dev tools at common breakpoints (375px iPhone SE/mini, 390–430px modern iPhone, 768px iPad portrait, 1024px+ desktop) per phase, before moving to the next phase. No automated visual regression tooling exists in this repo; rely on a documented checkpoint review (screenshot or live walkthrough) per phase since this skill set has no test suite for UI rendering.
