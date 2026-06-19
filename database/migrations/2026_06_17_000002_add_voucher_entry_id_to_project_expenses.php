<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_expenses', function (Blueprint $table) {
            $table->foreignId('voucher_entry_id')
                ->nullable()
                ->after('voucher_id')
                ->constrained('voucher_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_expenses', function (Blueprint $table) {
            $table->dropForeign(['voucher_entry_id']);
            $table->dropColumn('voucher_entry_id');
        });
    }
};
