<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LinkProjectExpensesToVouchers extends Migration
{
    /**
     * A voucher only becomes a project outflow once it is fully Paid, as a
     * single entry for the whole voucher — not one per payment. So the link
     * moves from voucher_payments.project_expense_id (per-payment) to
     * project_expenses.voucher_id (per-voucher).
     */
    public function up(): void
    {
        Schema::table('project_expenses', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()
                ->after('transfer_id')
                ->constrained('vouchers')
                ->nullOnDelete();
        });

        Schema::table('voucher_payments', function (Blueprint $table) {
            $table->dropForeign(['project_expense_id']);
            $table->dropColumn('project_expense_id');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_payments', function (Blueprint $table) {
            $table->foreignId('project_expense_id')->nullable()
                ->constrained('project_expenses')->nullOnDelete();
        });

        Schema::table('project_expenses', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropColumn('voucher_id');
        });
    }
}
