<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add due_date; widen status so we can use a richer lifecycle (not enum-locked on MySQL).
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('end_date');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE projects MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT \'active\'');
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE projects MODIFY COLUMN status ENUM(\'active\', \'completed\', \'on-hold\') NOT NULL DEFAULT \'active\'');
        }
    }
};
