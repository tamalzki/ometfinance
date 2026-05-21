<?php

use App\Models\Project;
use App\Models\ProjectAllocationLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_allocation_lines', function (Blueprint $table) {
            $table->string('row_kind', 20)->default('allocation')->after('project_id');
        });

        Project::query()->where('kind', 'external')->get()->each(function (Project $project) {
            if ($project->allocationLines()->exists()) {
                return;
            }
            foreach (Project::defaultExternalAllocationTemplate() as $sort => $row) {
                ProjectAllocationLine::create([
                    'project_id' => $project->id,
                    'label'      => $row['label'],
                    'percent'    => $row['percent'],
                    'row_kind'   => $row['row_kind'],
                    'sort_order' => $sort,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_allocation_lines', function (Blueprint $table) {
            $table->dropColumn('row_kind');
        });
    }
};
