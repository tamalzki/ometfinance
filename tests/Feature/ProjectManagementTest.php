<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_projects_page()
    {
        $user = User::factory()->create();

        // /projects is a landing redirect to the External tab.
        $this->actingAs($user)->get('/projects')->assertRedirect('/projects/external');

        $response = $this->actingAs($user)->get('/projects/external');
        $response->assertStatus(200);
        $response->assertSee('Projects');
    }

    public function test_authenticated_user_can_create_a_project()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/projects', [
            'name'           => 'North Wing Expansion',
            'kind'           => 'external',
            'client_name'    => 'Alpha Builders',
            'location'       => 'Pasig City',
            'status'         => 'in_progress',
            'contract_value' => 2500000.50,
            'start_date'     => now()->toDateString(),
            'end_date'       => now()->addMonth()->toDateString(),
            'due_date'       => now()->addWeeks(3)->toDateString(),
        ]);

        // redirects to the show page after creation
        $this->assertDatabaseHas('projects', [
            'name'        => 'North Wing Expansion',
            'client_name' => 'Alpha Builders',
            'location'    => 'Pasig City',
            'kind'        => 'external',
            'status'      => 'in_progress',
        ]);

        $project = Project::query()->where('name', 'North Wing Expansion')->first();
        $this->assertNotNull($project);
        $this->assertEquals(1, Project::count());
        $this->assertGreaterThan(0, $project->allocationLines()->count());
    }

    public function test_external_project_requires_client_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/projects', [
            'name'           => 'Project X',
            'kind'           => 'external',
            'status'         => 'active',
            'contract_value' => 2000000,
        ]);

        $response->assertSessionHasErrors('client_name');
        $this->assertEquals(0, Project::where('name', 'Project X')->count());
    }

    public function test_in_house_project_can_be_created_without_client_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/projects', [
            'name'   => 'Internal Build',
            'kind'   => 'in_house',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'name'        => 'Internal Build',
            'kind'        => 'in_house',
            'client_name' => 'Onemark (internal)',
        ]);
    }

    public function test_allocation_page_shows_running_cost_and_adjust_action()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/projects', [
            'name'           => 'Allocation Demo',
            'kind'           => 'external',
            'client_name'    => 'Beta Corp',
            'status'         => 'active',
            'contract_value' => 100000,
        ]);

        $project = Project::where('name', 'Allocation Demo')->first();

        $response = $this->actingAs($user)->get("/projects/{$project->id}/allocation");

        $response->assertOk();
        $response->assertSee('Allocated');
        $response->assertSee('Running cost');
        $response->assertSee('Adjust allocation');
    }

    public function test_allocation_percentages_can_be_adjusted()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/projects', [
            'name'           => 'Allocation Adjust',
            'kind'           => 'external',
            'client_name'    => 'Gamma LLC',
            'status'         => 'active',
            'contract_value' => 100000,
        ]);

        $project = Project::where('name', 'Allocation Adjust')->first();
        $sop     = $project->allocationLines()->where('label', 'SOP')->first();

        $this->actingAs($user)->put("/projects/{$project->id}/allocation", [
            'percents' => [$sop->id => 20],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(0.20, (float) $sop->refresh()->percent);
    }

    public function test_allocation_cannot_be_adjusted_past_100_percent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/projects', [
            'name'           => 'Allocation Overshoot',
            'kind'           => 'external',
            'client_name'    => 'Epsilon Co',
            'status'         => 'active',
            'contract_value' => 100000,
        ]);

        $project = Project::where('name', 'Allocation Overshoot')->first();
        $bucketLines = $project->allocationLines()
            ->where('row_kind', \App\Models\ProjectAllocationLine::KIND_ALLOCATION)
            ->get();

        $percents = $bucketLines->mapWithKeys(fn ($l) => [$l->id => $l->percent * 100])->all();
        $sop = $bucketLines->firstWhere('label', 'SOP');
        $percents[$sop->id] = 50; // pushes the bucket total well past 100%

        $response = $this->actingAs($user)->put("/projects/{$project->id}/allocation", [
            'percents' => $percents,
        ]);

        $response->assertSessionHasErrors('percents');
        $this->assertEquals(0.15, (float) $sop->refresh()->percent, 'Allocation must not change when over 100%.');
    }

    public function test_allocation_adjustment_appears_in_history()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/projects', [
            'name'           => 'Allocation History',
            'kind'           => 'external',
            'client_name'    => 'Delta Inc',
            'status'         => 'active',
            'contract_value' => 100000,
        ]);

        $project = Project::where('name', 'Allocation History')->first();
        $sop     = $project->allocationLines()->where('label', 'SOP')->first();

        $this->actingAs($user)->put("/projects/{$project->id}/allocation", [
            'percents' => [$sop->id => 25],
        ]);

        $response = $this->actingAs($user)->get("/projects/{$project->id}/allocation");

        $response->assertOk();
        $response->assertSee('Allocation history');
        $response->assertSee('15.00%');
        $response->assertSee('25.00%');
    }

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
}
