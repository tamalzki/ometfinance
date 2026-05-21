<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            // Why: every intercompany movement should be classifiable so the
            // central register stays readable and filterable, and (optionally)
            // tied to the projects it affects on either side.
            $table->string('purpose', 40)->nullable()->after('memo');
            $table->text('reason')->nullable()->after('purpose');
            $table->foreignId('from_project_id')->nullable()
                ->after('from_account_id')
                ->constrained('projects')
                ->nullOnDelete();
            $table->foreignId('to_project_id')->nullable()
                ->after('to_account_id')
                ->constrained('projects')
                ->nullOnDelete();
        });

        Schema::table('project_collections', function (Blueprint $table) {
            // Why: when a transfer brings money INTO a project, we create a
            // collection row linked back to its transfer so deleting/reversing
            // the transfer cascades cleanly.
            $table->foreignId('transfer_id')->nullable()
                ->after('bank_account_id')
                ->constrained('transfers')
                ->nullOnDelete();
        });

        Schema::table('project_expenses', function (Blueprint $table) {
            // Why: symmetric to collections — transfer taking money OUT of a
            // project becomes a project expense linked back to its transfer.
            $table->foreignId('transfer_id')->nullable()
                ->after('bank_account_id')
                ->constrained('transfers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_expenses', function (Blueprint $table) {
            $table->dropForeign(['transfer_id']);
            $table->dropColumn('transfer_id');
        });

        Schema::table('project_collections', function (Blueprint $table) {
            $table->dropForeign(['transfer_id']);
            $table->dropColumn('transfer_id');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['from_project_id']);
            $table->dropForeign(['to_project_id']);
            $table->dropColumn(['purpose', 'reason', 'from_project_id', 'to_project_id']);
        });
    }
};
