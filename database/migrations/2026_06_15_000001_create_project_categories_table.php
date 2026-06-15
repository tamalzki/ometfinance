<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateProjectCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('project_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('project_categories')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        $now = now();

        $topLevel = ['Materials', 'Labor', 'Payroll', 'Opex'];
        $ids = [];

        foreach ($topLevel as $name) {
            $ids[$name] = DB::table('project_categories')->insertGetId([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (['Internal', 'Subcon'] as $name) {
            DB::table('project_categories')->insert([
                'parent_id' => $ids['Labor'],
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('project_categories');
    }
}
