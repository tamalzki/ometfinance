<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCategoryIdToVouchersAndExpenses extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()
                ->after('transaction_type')
                ->constrained('project_categories')
                ->nullOnDelete();
        });

        Schema::table('project_expenses', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()
                ->after('category')
                ->constrained('project_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::table('project_expenses', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
}
