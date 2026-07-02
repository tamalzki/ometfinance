# Running Cost by Bucket — Design Spec

**Date:** 2026-07-02  
**Status:** Approved

## Problem

The external project allocation tab shows a single "Running Cost" total (`$project->totalExpenses()`) in the header, and a `—` placeholder in every allocation row. The comment in the view explicitly notes: *"not yet broken down per allocation bucket — no category↔bucket mapping exists yet."*

Users need to see actual spend per cost bucket (SOP, Direct Costs, OCM, Commission, Capital Cost, Admin Cost) drawn from real paid/partial-paid voucher amounts.

## Data Flow (existing)

When a voucher is paid (fully or partially), `VoucherService::syncProjectOutflow` already creates/updates `ProjectExpense` rows:
- `project_id` — the project the expense belongs to
- `category_id` — the `ProjectCategory` assigned to the voucher or its entry
- `amount` — the amount actually paid (proportioned for partials)

The data is already there. This feature adds the mapping layer and surfaces per-bucket totals in the view.

## Approach

Add a `running_cost_bucket` column to `project_categories`. Tag each relevant category. Aggregate `ProjectExpense` amounts per bucket in PHP (encrypted column — can't use SQL SUM). Display per-bucket totals in the allocation table.

---

## Schema Changes

### 1. Add `running_cost_bucket` to `project_categories`

```
project_categories.running_cost_bucket  nullable string
```

Valid values: `sop`, `direct_cost`, `ocm`, `commission`, `capital_cost`, `admin_cost`, `null`.  
`null` = not mapped to any running cost bucket.

### 2. Seed existing categories

| Category Name | ID | Bucket |
|---|---|---|
| Direct Labor | 2 | `direct_cost` |
| Direct Materials | 9 | `direct_cost` |
| Overhead Cost - Project | 28 | `ocm` |
| SOP | 57 | `sop` |

Children of tagged parent categories inherit the parent's bucket at query time via `COALESCE(child.bucket, parent.bucket)` logic in PHP. No need to tag every leaf individually.

**Exception:** Direct Materials (id 9) is a leaf whose parent (Materials, id 1) has no bucket. It is tagged directly at the leaf level.

### 3. Create 3 new categories

| Name | Parent | Bucket |
|---|---|---|
| Commission Project | *(top-level)* | `commission` |
| Loans Payable | *(top-level)* | `null` |
| Interest Expense - Loans | Loans Payable | `capital_cost` |

`Loans Payable` has no bucket itself — only its specific sub-category `Interest Expense - Loans` contributes to Capital Cost. Any future sub-categories added under Loans Payable that should NOT contribute to capital_cost simply remain `null`.

**Admin Cost** bucket is defined in the mapping but no categories are tagged to it yet. It will always display ₱0.00 until categories are assigned. This is intentional — admin cost is often split across other areas.

---

## Computation

### `Project::runningCostsByBucket(): array`

New public method on the `Project` model. Iterates `$this->expenses` in PHP (required because `amount` is an encrypted column — SQL `SUM()` cannot operate on it). The `expenses.categoryRef.parent` relation is already eager-loaded by `ProjectController::loadProjectData()`, so this method adds zero extra queries when called from the allocation action.

For each expense, the effective bucket is:
```
expense.category.running_cost_bucket
  ?? expense.category.parent?.running_cost_bucket
  ?? null  (excluded from all buckets)
```

Returns an array with all 6 buckets always present, defaulting to `0.0`:

```php
[
    'sop'          => float,
    'direct_cost'  => float,
    'ocm'          => float,
    'commission'   => float,
    'capital_cost' => float,
    'admin_cost'   => float,
]
```

Expenses with no resolvable bucket are silently excluded (they already appear in the grand total via `totalExpenses()`).

---

## Label → Bucket Mapping

Hard-coded 6-entry map in `ProjectController::allocation()`. Allocation line labels are seeded once and are not user-editable (the Adjust modal only edits percentages and amounts, not labels), so this is stable.

```php
private const ALLOCATION_BUCKET_MAP = [
    'SOP'          => 'sop',
    'Direct Costs' => 'direct_cost',
    'OCM'          => 'ocm',
    'Commission'   => 'commission',
    'Capital Cost' => 'capital_cost',
    'Admin Cost'   => 'admin_cost',
];
```

The controller passes `$runningCostsByBucket` (the array) and `$bucketMap` (label→key) to the view. The view resolves `$bucketMap[$line->label] ?? null` for each row to look up the right total.

---

## View Changes (`allocation.blade.php`)

### Header cell
- Remove the `<i data-lucide="info" ...>` tooltip that says "No category tagging yet…"
- Keep the grand total in the sub-header (`$runningCost = array_sum($runningCostsByBucket)`)

### Per-row Running Cost cell
Replace the current placeholder:
```blade
{{-- before --}}
<td ... title="Not tagged to a bucket yet…">—</td>

{{-- after --}}
@php $bucketKey = $bucketMap[$line->label] ?? null; @endphp
@if ($bucketKey && !$isKpi)
<td ... class="... text-rose-600 font-semibold">
    ₱{{ number_format($runningCostsByBucket[$bucketKey] ?? 0, 2) }}
</td>
@else
<td ...>—</td>
@endif
```

KPI rows (EBIT, Net Income) keep showing `—`.

### Subtotal row
Keep `₱{{ number_format($runningCost, 2) }}` — now derived from `array_sum($runningCostsByBucket)` rather than `$project->totalExpenses()` so it matches the per-row sum exactly. (Expenses with no bucket are excluded from both the row totals and the subtotal — this is intentional since untagged expenses shouldn't count against any specific bucket.)

> **Note:** `$project->totalExpenses()` (used in the project overview's `netCashPosition()`) is unchanged. The subtotal on the allocation tab now shows only bucket-mapped spend; a small discrepancy from the overview total is expected and acceptable until all categories are tagged.

---

## Controller Changes (`ProjectController::allocation()`)

1. Compute `$runningCostsByBucket = $project->runningCostsByBucket()`
2. Compute `$runningCost = array_sum($runningCostsByBucket)` (replaces `$project->totalExpenses()` in the view)
3. Pass `$runningCostsByBucket` and `$bucketMap` (the label→key constant) to the view

---

## Files Changed

| File | Change |
|---|---|
| `database/migrations/YYYY_MM_DD_add_running_cost_bucket_to_project_categories.php` | New — adds column, seeds buckets, creates 3 new categories |
| `app/Models/Project.php` | Add `runningCostsByBucket(): array` |
| `app/Models/ProjectCategory.php` | Add `running_cost_bucket` to `$fillable` |
| `app/Http/Controllers/ProjectController.php` | Compute and pass bucket data to `allocation()` |
| `resources/views/projects/external/allocation.blade.php` | Replace `—` placeholders, remove stale tooltip |

---

## Out of Scope

- Admin Cost category tagging (deferred — user confirmed "later")
- SOP breakdown (SOP sub-categories display as one total under SOP row)
- Project overview page changes (running cost by bucket stays on allocation tab only)
- In-house project allocation tab (separate tab, no bucket mapping needed yet)
