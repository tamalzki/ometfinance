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
}
