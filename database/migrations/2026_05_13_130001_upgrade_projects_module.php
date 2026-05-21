<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add kind + code to projects
        Schema::table('projects', function (Blueprint $table) {
            $table->string('kind', 20)->default('external')->after('name'); // in_house | external
            $table->string('code', 50)->nullable()->after('kind');
        });

        // Project collections (inflow events — irregular, per collection)
        Schema::create('project_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->date('collected_on');
            $table->decimal('amount', 15, 2);
            $table->string('reference', 100)->nullable(); // cheque no, OR no, etc.
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Project expenses (money out per project)
        Schema::create('project_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->date('spent_on');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->string('vendor_ref', 100)->nullable(); // PO no, vendor
            $table->string('category', 60)->nullable(); // materials, subcon, opex, payroll ...
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Allocation template lines (external projects only)
        Schema::create('project_allocation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('label');          // SOP, Direct Costs, OCM …
            $table->decimal('percent', 8, 4); // 0.15 = 15 %
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_allocation_lines');
        Schema::dropIfExists('project_expenses');
        Schema::dropIfExists('project_collections');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['kind', 'code']);
        });
    }
};
