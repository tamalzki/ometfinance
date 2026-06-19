<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A voucher credit entry tagged with a project represents money coming
     * INTO that project (e.g. a collection/reimbursement routed through a
     * disbursement voucher). Mirrors project_expenses.voucher_id /
     * voucher_entry_id so VoucherService can sync inflow rows the same way
     * it already syncs outflow rows from debit entries.
     */
    public function up(): void
    {
        Schema::table('project_collections', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()
                ->after('transfer_id')
                ->constrained('vouchers')
                ->nullOnDelete();
            $table->foreignId('voucher_entry_id')->nullable()
                ->after('voucher_id')
                ->constrained('voucher_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_collections', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropForeign(['voucher_entry_id']);
            $table->dropColumn(['voucher_id', 'voucher_entry_id']);
        });
    }
};
