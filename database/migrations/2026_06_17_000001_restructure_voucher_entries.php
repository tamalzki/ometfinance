<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_entries', function (Blueprint $table) {
            $table->dropColumn(['debit_amount', 'credit_amount']);
            $table->enum('entry_type', ['debit', 'credit'])->default('debit')->after('description');
            $table->decimal('amount', 15, 2)->default(0)->after('entry_type');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_entries', function (Blueprint $table) {
            $table->dropColumn(['entry_type', 'amount']);
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
        });
    }
};
