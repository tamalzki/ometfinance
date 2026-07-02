<?php

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
