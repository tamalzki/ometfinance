<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Entity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFundingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private BankAccount $sourceAccount;
    private BankAccount $receivingAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();

        $entity = Entity::create(['name' => 'Onemark', 'slug' => 'onemark']);
        $this->sourceAccount = BankAccount::create([
            'entity_id' => $entity->id, 'name' => 'BPI Main', 'bank_name' => 'BPI', 'opening_balance' => 0,
        ]);
        $this->receivingAccount = BankAccount::create([
            'entity_id' => $entity->id, 'name' => 'BDO Project', 'bank_name' => 'BDO', 'opening_balance' => 0,
        ]);
    }

    private function makeProject(string $kind, string $name = 'Test Project'): Project
    {
        return Project::create([
            'name'   => $name,
            'kind'   => $kind,
            'status' => 'active',
            'client_name' => $kind === 'external' ? 'Client Co' : 'Onemark (internal)',
        ]);
    }

    public function test_funding_books_transfer_ledger_legs_and_project_inflow()
    {
        $project = $this->makeProject('in_house');

        $response = $this->actingAs($this->admin)->post("/projects/{$project->id}/funding", [
            'from_account_id' => $this->sourceAccount->id,
            'to_account_id'   => $this->receivingAccount->id,
            'date'            => now()->toDateString(),
            'amount'          => 50000,
            'notes'           => 'Payroll week 24',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseCount('transfers', 1);
        $this->assertDatabaseCount('ledger_entries', 2);

        $project->refresh()->load('collections.transfer');
        $this->assertCount(1, $project->collections);
        $this->assertTrue($project->collections->first()->isFromTransfer());
        $this->assertSame(50000.0, $project->totalBorrowed());
        $this->assertSame(0.0, $project->totalClientCollected());
    }

    public function test_funding_with_source_project_records_outflow_on_that_project()
    {
        $inHouse  = $this->makeProject('in_house', 'Warehouse Build');
        $external = $this->makeProject('external', 'Mall Fit-out');

        $this->actingAs($this->admin)->post("/projects/{$inHouse->id}/funding", [
            'from_account_id' => $this->sourceAccount->id,
            'to_account_id'   => $this->receivingAccount->id,
            'from_project_id' => $external->id,
            'date'            => now()->toDateString(),
            'amount'          => 25000,
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, $inHouse->refresh()->collections);
        $this->assertCount(1, $external->refresh()->expenses);
        $this->assertSame(25000.0, (float) $external->expenses->first()->amount);
    }

    public function test_project_cannot_fund_itself()
    {
        $project = $this->makeProject('in_house');

        $this->actingAs($this->admin)->post("/projects/{$project->id}/funding", [
            'from_account_id' => $this->sourceAccount->id,
            'to_account_id'   => $this->receivingAccount->id,
            'from_project_id' => $project->id,
            'date'            => now()->toDateString(),
            'amount'          => 1000,
        ])->assertSessionHasErrors('from_project_id');

        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_funding_requires_two_different_accounts()
    {
        $project = $this->makeProject('in_house');

        $this->actingAs($this->admin)->post("/projects/{$project->id}/funding", [
            'from_account_id' => $this->sourceAccount->id,
            'to_account_id'   => $this->sourceAccount->id,
            'date'            => now()->toDateString(),
            'amount'          => 1000,
        ])->assertSessionHasErrors('from_account_id');

        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_manual_collection_is_rejected_for_in_house_projects()
    {
        $project = $this->makeProject('in_house');

        $this->actingAs($this->admin)->post("/projects/{$project->id}/collections", [
            'collected_on' => now()->toDateString(),
            'amount'       => 9999,
        ])->assertSessionHasErrors('collection');

        $this->assertCount(0, $project->refresh()->collections);
    }

    public function test_manual_collection_still_works_for_external_projects()
    {
        $project = $this->makeProject('external');

        $this->actingAs($this->admin)->post("/projects/{$project->id}/collections", [
            'collected_on' => now()->toDateString(),
            'amount'       => 150000,
            'reference'    => 'OR-1001',
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(150000.0, $project->totalClientCollected());
        $this->assertSame(0.0, $project->totalBorrowed());
    }

    public function test_collection_deductions_are_computed_server_side()
    {
        $project = $this->makeProject('external');

        $this->actingAs($this->admin)->post("/projects/{$project->id}/collections", [
            'collected_on'            => now()->toDateString(),
            'amount'                  => 100000,
            'client_type'             => 'government',
            'transaction_type'        => 'services',
            'vat_rate'                => 5,
            'wht_rate'                => 2,
            'retention_rate'          => 10,
            'recoupment_rate'         => 15,
            'other_deductions_amount' => 500,
        ])->assertSessionHasNoErrors();

        $collection = $project->refresh()->collections->first();

        $this->assertSame(5000.0, (float) $collection->vat_amount);
        $this->assertSame(2000.0, (float) $collection->wht_amount);
        $this->assertSame(10000.0, (float) $collection->retention_amount);
        $this->assertSame(15000.0, (float) $collection->recoupment_amount);
        $this->assertSame(500.0, (float) $collection->other_deductions_amount);
        $this->assertSame(32500.0, $collection->totalDeductions());
        $this->assertSame(67500.0, $collection->netAmount());
        $this->assertSame(67500.0, $project->totalClientCollectedNet());
    }

    public function test_cfo_cannot_record_funding()
    {
        $cfo = User::factory()->create(['role' => 'cfo']);
        $project = $this->makeProject('in_house');

        $this->actingAs($cfo)->post("/projects/{$project->id}/funding", [
            'from_account_id' => $this->sourceAccount->id,
            'to_account_id'   => $this->receivingAccount->id,
            'date'            => now()->toDateString(),
            'amount'          => 1000,
        ])->assertForbidden();
    }

    public function test_inflow_pages_render_kind_specific_views()
    {
        $inHouse  = $this->makeProject('in_house', 'Warehouse Build');
        $external = $this->makeProject('external', 'Mall Fit-out');

        $this->actingAs($this->admin)->get("/projects/{$inHouse->id}/inflow")
            ->assertOk()
            ->assertSee('No funding yet')
            ->assertSee('borrowing from another account');

        $this->actingAs($this->admin)->get("/projects/{$external->id}/inflow")
            ->assertOk()
            ->assertSee('No inflows yet');
    }

    public function test_populated_inflow_pages_show_type_breakdown()
    {
        $inHouse  = $this->makeProject('in_house', 'Warehouse Build');
        $external = $this->makeProject('external', 'Mall Fit-out');

        // External: one client collection + one borrowing.
        $this->actingAs($this->admin)->post("/projects/{$external->id}/collections", [
            'collected_on' => now()->toDateString(),
            'amount'       => 100000,
            'reference'    => 'OR-2001',
        ]);
        $this->actingAs($this->admin)->post("/projects/{$external->id}/funding", [
            'from_account_id' => $this->sourceAccount->id,
            'to_account_id'   => $this->receivingAccount->id,
            'date'            => now()->toDateString(),
            'amount'          => 40000,
        ]);

        $this->actingAs($this->admin)->get("/projects/{$external->id}/inflow")
            ->assertOk()
            ->assertSee('Borrowed / support')
            ->assertSee('Collections')
            ->assertSee('Total inflow');

        // In-house: support from the external project.
        $this->actingAs($this->admin)->post("/projects/{$inHouse->id}/funding", [
            'from_account_id' => $this->sourceAccount->id,
            'to_account_id'   => $this->receivingAccount->id,
            'from_project_id' => $external->id,
            'date'            => now()->toDateString(),
            'amount'          => 15000,
        ]);

        $this->actingAs($this->admin)->get("/projects/{$inHouse->id}/inflow")
            ->assertOk()
            ->assertSee('Borrowed from accounts')
            ->assertSee('Project support')
            ->assertSee('support from Mall Fit-out')
            ->assertSee('Total funded');
    }
}
