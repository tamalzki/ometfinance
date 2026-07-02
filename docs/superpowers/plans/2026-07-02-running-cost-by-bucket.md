# Running Cost by Bucket — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Break down the "Running Cost" column on the external project allocation tab so each row (SOP, Direct Costs, OCM, Commission, Capital Cost, Admin Cost) shows the actual paid voucher amount for that cost bucket.

**Architecture:** Add a `running_cost_bucket` nullable string to `project_categories`, seed existing categories and create three new ones, then add `Project::runningCostsByBucket()` that iterates the already-eager-loaded `expenses.categoryRef.parent` relation and sums per bucket in PHP (required because `amount` is encrypted). The controller computes the bucket totals and passes them to the allocation view, which replaces the current `—` placeholder cells.

**Tech Stack:** Laravel 8, PHP 8, Blade, PHPUnit 9 — no new packages.

## Global Constraints

- `amount` on `ProjectExpense` is an `encrypted` cast — never use SQL `SUM()` on it; always sum in PHP.
- `expenses.categoryRef.parent` is already eager-loaded by `ProjectController::loadProjectData()` — `runningCostsByBucket()` must not issue additional queries.
- Tests use `RefreshDatabase` — all DB state is per-test.
- Migration naming convention: `YYYY_MM_DD_XXXXXX_snake_case_description.php`.
- Run tests with: `php artisan test`

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `database/migrations/2026_07_02_000001_add_running_cost_bucket_to_project_categories.php` | **Create** | Adds column, seeds existing categories, creates 3 new ones |
| `app/Models/ProjectCategory.php` | **Modify** | Add `running_cost_bucket` to `$fillable` |
| `app/Models/Project.php` | **Modify** | Add `runningCostsByBucket(): array` |
| `app/Http/Controllers/ProjectController.php` | **Modify** | Compute and pass bucket data in `showAllocation()` |
| `resources/views/projects/external/allocation.blade.php` | **Modify** | Replace `—` placeholders, remove stale tooltip, remove inline `$runningCost` assignment |
| `tests/Feature/ProjectManagementTest.php` | **Modify** | Two new tests: unit-style bucket computation, HTTP integration |

---

## Task 1: Migration — add bucket column, seed categories, create new ones

**Files:**
- Create: `database/migrations/2026_07_02_000001_add_running_cost_bucket_to_project_categories.php`

**Interfaces:**
- Produces: `project_categories.running_cost_bucket` nullable string column; seeded values for Direct Labor, Direct Materials, Overhead Cost - Project, SOP; three new rows: Commission Project (`commission`), Loans Payable (`null`), Interest Expense - Loans (`capital_cost`).

- [ ] **Step 1: Create the migration file**

```php
<?php
// database/migrations/2026_07_02_000001_add_running_cost_bucket_to_project_categories.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRunningCostBucketToProjectCategories extends Migration
{
    public function up(): void
    {
        Schema::table('project_categories', function (Blueprint $table) {
            $table->string('running_cost_bucket')->nullable()->after('parent_id');
        });

        // Seed bucket assignments by name (safer than hardcoding IDs across environments)
        $bucketsByName = [
            'Direct Labor'           => 'direct_cost',
            'Direct Materials'       => 'direct_cost',
            'Overhead Cost - Project'=> 'ocm',
            'SOP'                    => 'sop',
        ];

        foreach ($bucketsByName as $name => $bucket) {
            DB::table('project_categories')
                ->where('name', $name)
                ->update(['running_cost_bucket' => $bucket]);
        }

        // Create the three new categories
        $now = now();

        DB::table('project_categories')->insert([
            'name'                => 'Commission Project',
            'running_cost_bucket' => 'commission',
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $loansPayableId = DB::table('project_categories')->insertGetId([
            'name'                => 'Loans Payable',
            'running_cost_bucket' => null,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        DB::table('project_categories')->insert([
            'parent_id'           => $loansPayableId,
            'name'                => 'Interest Expense - Loans',
            'running_cost_bucket' => 'capital_cost',
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('project_categories')
            ->whereIn('name', ['Interest Expense - Loans', 'Commission Project', 'Loans Payable'])
            ->delete();

        Schema::table('project_categories', function (Blueprint $table) {
            $table->dropColumn('running_cost_bucket');
        });
    }
}
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected output: `Running migrations... 2026_07_02_000001_add_running_cost_bucket_to_project_categories ........ XX ms DONE`

- [ ] **Step 3: Verify in tinker**

```bash
php artisan tinker --execute="
use App\Models\ProjectCategory;
\$check = ['Direct Labor','Direct Materials','Overhead Cost - Project','SOP','Commission Project','Interest Expense - Loans'];
ProjectCategory::whereIn('name',\$check)->get(['name','running_cost_bucket'])->each(fn(\$c)=>print(\$c->name.' => '.\$c->running_cost_bucket.PHP_EOL));
"
```

Expected: each name prints its bucket (SOP → sop, Direct Labor → direct_cost, etc.)

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_02_000001_add_running_cost_bucket_to_project_categories.php
git commit -m "feat: add running_cost_bucket to project_categories, seed buckets, add new categories"
```

---

## Task 2: Model — `ProjectCategory.$fillable` + `Project::runningCostsByBucket()`

**Files:**
- Modify: `app/Models/ProjectCategory.php`
- Modify: `app/Models/Project.php`
- Test: `tests/Feature/ProjectManagementTest.php`

**Interfaces:**
- Consumes: `project_categories.running_cost_bucket` (from Task 1)
- Produces: `Project::runningCostsByBucket(): array` — keys `sop`, `direct_cost`, `ocm`, `commission`, `capital_cost`, `admin_cost`, values are `float`, all six keys always present.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/ProjectManagementTest.php` (at the end of the class, before the closing `}`):

```php
public function test_running_costs_by_bucket_groups_expenses_correctly(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)->post('/projects', [
        'name'           => 'Bucket Unit Test',
        'kind'           => 'external',
        'client_name'    => 'Test Client',
        'status'         => 'active',
        'contract_value' => 100000,
    ]);
    $project = \App\Models\Project::where('name', 'Bucket Unit Test')->first();

    $sopParent   = \App\Models\ProjectCategory::create(['name' => 'SOP Root T2', 'running_cost_bucket' => 'sop']);
    $sopChild    = \App\Models\ProjectCategory::create(['name' => 'SOP Sub T2', 'parent_id' => $sopParent->id]);
    $laborParent = \App\Models\ProjectCategory::create(['name' => 'Labor Root T2', 'running_cost_bucket' => 'direct_cost']);
    $noTag       = \App\Models\ProjectCategory::create(['name' => 'No Bucket T2']);

    \App\Models\ProjectExpense::create(['project_id' => $project->id, 'category_id' => $sopParent->id, 'amount' => 1000.00, 'spent_on' => now()->toDateString()]);
    \App\Models\ProjectExpense::create(['project_id' => $project->id, 'category_id' => $sopChild->id,  'amount' => 500.00,  'spent_on' => now()->toDateString()]);
    \App\Models\ProjectExpense::create(['project_id' => $project->id, 'category_id' => $laborParent->id,'amount' => 2000.00, 'spent_on' => now()->toDateString()]);
    \App\Models\ProjectExpense::create(['project_id' => $project->id, 'category_id' => $noTag->id,      'amount' => 300.00,  'spent_on' => now()->toDateString()]);

    $project->load('expenses.categoryRef.parent');
    $buckets = $project->runningCostsByBucket();

    $this->assertEqualsWithDelta(1500.00, $buckets['sop'],          0.01); // parent + child
    $this->assertEqualsWithDelta(2000.00, $buckets['direct_cost'],  0.01);
    $this->assertEqualsWithDelta(0.00,    $buckets['ocm'],          0.01);
    $this->assertEqualsWithDelta(0.00,    $buckets['commission'],   0.01);
    $this->assertEqualsWithDelta(0.00,    $buckets['capital_cost'], 0.01);
    $this->assertEqualsWithDelta(0.00,    $buckets['admin_cost'],   0.01);
    // untagged expense (300) excluded from all buckets
    $this->assertEqualsWithDelta(3500.00, array_sum($buckets),      0.01);
}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
php artisan test --filter=test_running_costs_by_bucket_groups_expenses_correctly
```

Expected: FAIL — `Call to undefined method App\Models\Project::runningCostsByBucket()`

- [ ] **Step 3: Add `running_cost_bucket` to `ProjectCategory.$fillable`**

Open `app/Models/ProjectCategory.php`. Change:

```php
protected $fillable = ['parent_id', 'name'];
```

To:

```php
protected $fillable = ['parent_id', 'name', 'running_cost_bucket'];
```

- [ ] **Step 4: Add `runningCostsByBucket()` to `Project`**

Open `app/Models/Project.php`. Add this method after `totalExpenses()`:

```php
public function runningCostsByBucket(): array
{
    $buckets = [
        'sop'          => 0.0,
        'direct_cost'  => 0.0,
        'ocm'          => 0.0,
        'commission'   => 0.0,
        'capital_cost' => 0.0,
        'admin_cost'   => 0.0,
    ];

    foreach ($this->expenses as $expense) {
        $amount = (float) $expense->amount;
        if ($amount <= 0) {
            continue;
        }

        $cat = $expense->categoryRef;
        if (! $cat) {
            continue;
        }

        $bucket = $cat->running_cost_bucket ?? $cat->parent?->running_cost_bucket;
        if ($bucket !== null && array_key_exists($bucket, $buckets)) {
            $buckets[$bucket] += $amount;
        }
    }

    return $buckets;
}
```

- [ ] **Step 5: Run the test to confirm it passes**

```bash
php artisan test --filter=test_running_costs_by_bucket_groups_expenses_correctly
```

Expected: PASS

- [ ] **Step 6: Run the full test suite to check for regressions**

```bash
php artisan test
```

Expected: all existing tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Models/ProjectCategory.php app/Models/Project.php tests/Feature/ProjectManagementTest.php
git commit -m "feat: add runningCostsByBucket() to Project model"
```

---

## Task 3: Controller + View — wire up and display per-bucket amounts

**Files:**
- Modify: `app/Http/Controllers/ProjectController.php` (method `showAllocation`, lines ~211–231)
- Modify: `resources/views/projects/external/allocation.blade.php`
- Test: `tests/Feature/ProjectManagementTest.php`

**Interfaces:**
- Consumes: `Project::runningCostsByBucket(): array` (from Task 2); `$bucketMap` constant (defined inline in controller)
- Produces: Allocation view shows per-row Running Cost amounts; header total matches sum of buckets.

- [ ] **Step 1: Write the failing integration test**

Add to `tests/Feature/ProjectManagementTest.php`:

```php
public function test_allocation_page_shows_per_bucket_running_costs(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)->post('/projects', [
        'name'           => 'Bucket Display Test',
        'kind'           => 'external',
        'client_name'    => 'Display Corp',
        'status'         => 'active',
        'contract_value' => 500000,
    ]);
    $project = \App\Models\Project::where('name', 'Bucket Display Test')->first();

    $sopCat   = \App\Models\ProjectCategory::create(['name' => 'SOP T3', 'running_cost_bucket' => 'sop']);
    $laborCat = \App\Models\ProjectCategory::create(['name' => 'Labor T3', 'running_cost_bucket' => 'direct_cost']);

    \App\Models\ProjectExpense::create(['project_id' => $project->id, 'category_id' => $sopCat->id,   'amount' => 12000.00, 'spent_on' => now()->toDateString()]);
    \App\Models\ProjectExpense::create(['project_id' => $project->id, 'category_id' => $laborCat->id, 'amount' => 35000.00, 'spent_on' => now()->toDateString()]);

    $response = $this->actingAs($user)->get("/projects/{$project->id}/allocation");

    $response->assertOk();
    // Both bucket amounts appear in the table
    $response->assertSee('12,000.00');
    $response->assertSee('35,000.00');
    // Grand total (47,000) appears in the subtotal row
    $response->assertSee('47,000.00');
}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
php artisan test --filter=test_allocation_page_shows_per_bucket_running_costs
```

Expected: FAIL — page exists (assertOk passes) but `12,000.00` not found in output.

- [ ] **Step 3: Update `showAllocation()` in `ProjectController`**

Open `app/Http/Controllers/ProjectController.php`. Find the `showAllocation()` method (~line 211). Replace the final `return view(...)` call so the method reads:

```php
public function showAllocation(Project $project)
{
    if (! $project->isExternal()) {
        return redirect()->route('projects.show.overview', $project);
    }

    $data = $this->loadProjectData($project);

    $data['allocationHistory'] = \App\Models\AuditLog::where('auditable_type', ProjectAllocationLine::class)
        ->whereIn('auditable_id', $project->allocationLines->pluck('id'))
        ->where('event', 'updated')
        ->whereNotNull('new_values->percent')
        ->with('user')
        ->latest('created_at')
        ->latest('id')
        ->limit(50)
        ->get();

    $runningCostsByBucket = $project->runningCostsByBucket();

    $data['runningCostsByBucket'] = $runningCostsByBucket;
    $data['runningCost']          = array_sum($runningCostsByBucket);
    $data['bucketMap'] = [
        'SOP'          => 'sop',
        'Direct Costs' => 'direct_cost',
        'OCM'          => 'ocm',
        'Commission'   => 'commission',
        'Capital Cost' => 'capital_cost',
        'Admin Cost'   => 'admin_cost',
    ];

    return view('projects.external.allocation', $data);
}
```

- [ ] **Step 4: Update the allocation view**

Open `resources/views/projects/external/allocation.blade.php`.

**4a — Remove the inline `$runningCost` assignment from the `@php` block at the top.**

Find and remove this line from the `@php` block (around line 16):
```blade
    $runningCost      = $project->totalExpenses();
```

The `@php` block's final form should end with:
```php
    $runningCost      = $project->totalExpenses();   // ← DELETE THIS LINE
```

**4b — Remove the stale info icon from the "Running cost" column header.**

Find this block (around line 70–74):
```blade
                            <th class="sticky left-[21.5rem] z-20 w-[7.5rem] min-w-[7.5rem] border-r border-slate-200 bg-rose-50/40 px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-rose-700">
                                <div class="inline-flex items-center gap-1">
                                    Running cost
                                    
                                    <i data-lucide="info" class="h-3 w-3 text-rose-500" title="No category tagging yet — every voucher's amount is lumped here as one general total until vouchers can be mapped to a bucket."></i>
                                </div>
                                <div class="mt-0.5 text-[11px] font-bold normal-case tabular-nums text-rose-600">₱{{ number_format($runningCost, 2) }}</div>
                            </th>
```

Replace with:
```blade
                            <th class="sticky left-[21.5rem] z-20 w-[7.5rem] min-w-[7.5rem] border-r border-slate-200 bg-rose-50/40 px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-rose-700">
                                Running cost
                                <div class="mt-0.5 text-[11px] font-bold normal-case tabular-nums text-rose-600">₱{{ number_format($runningCost, 2) }}</div>
                            </th>
```

**4c — Replace the per-row `—` placeholder cell with actual bucket amounts.**

Find this cell (around line 123–125 inside the `@foreach ($allocLines as $line)` loop):
```blade
                                <td class="sticky left-[21.5rem] z-10 w-[7.5rem] min-w-[7.5rem] border-r border-slate-200 px-3 py-2.5 text-right text-[12px] text-slate-300 {{ $isKpi ? 'bg-amber-50/30' : 'bg-white' }}" title="Not tagged to a bucket yet — see the general total under Running cost.">
                                    —
                                </td>
```

Replace with:
```blade
                                @php $bucketKey = $isKpi ? null : ($bucketMap[$line->label] ?? null); @endphp
                                <td class="sticky left-[21.5rem] z-10 w-[7.5rem] min-w-[7.5rem] border-r border-slate-200 px-3 py-2.5 text-right {{ $isKpi ? 'bg-amber-50/30 text-slate-300 text-[12px]' : 'bg-white font-semibold text-rose-600 text-[13px]' }}">
                                    @if ($bucketKey)
                                        ₱{{ number_format($runningCostsByBucket[$bucketKey] ?? 0, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
```

- [ ] **Step 5: Run the integration test**

```bash
php artisan test --filter=test_allocation_page_shows_per_bucket_running_costs
```

Expected: PASS

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

Expected: all tests pass. If `test_allocation_page_shows_running_cost_and_adjust_action` fails because it checks for the info icon text, update that test to remove the `assertSee` for the old tooltip string (if any).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ProjectController.php \
        resources/views/projects/external/allocation.blade.php \
        tests/Feature/ProjectManagementTest.php
git commit -m "feat: display per-bucket running costs on external project allocation tab"
```

---

## Self-Review

### Spec Coverage

| Spec requirement | Task |
|---|---|
| All paid/partial voucher amounts recorded as outflow | Already handled by VoucherService — not changed |
| Partial vouchers record partial amount | Already handled — not changed |
| `running_cost_bucket` column + seeded categories | Task 1 |
| New categories: Commission Project, Loans Payable, Interest Expense - Loans | Task 1 |
| `Project::runningCostsByBucket()` — PHP sum, parent inheritance | Task 2 |
| `running_cost_bucket` in `$fillable` | Task 2 |
| Controller passes `$runningCostsByBucket`, `$runningCost`, `$bucketMap` | Task 3 |
| View: remove info icon | Task 3 step 4b |
| View: replace `—` with bucket amounts | Task 3 step 4c |
| View: remove inline `$runningCost` assignment | Task 3 step 4a |
| KPI rows keep `—` | Task 3 step 4c (`$isKpi ? null` guards the lookup) |
| Admin Cost shows ₱0.00 (no categories tagged yet) | Falls out naturally — bucket defaults to 0.0 |
| Grand total in subtotal row = sum of all buckets | `array_sum($runningCostsByBucket)` in Task 3 |

### Placeholder Scan

None found. All steps contain exact code.

### Type Consistency

- `runningCostsByBucket()` defined in Task 2, consumed in Task 3 as `$project->runningCostsByBucket()` — matches.
- Return type `array` with string keys, float values — used as `$runningCostsByBucket[$bucketKey] ?? 0` in view — correct.
- `$bucketMap` defined as `string → string` in controller, used as `$bucketMap[$line->label] ?? null` in view — correct.
- `$runningCost` passed as `array_sum(...)` float from controller; view uses `number_format($runningCost, 2)` — correct.
