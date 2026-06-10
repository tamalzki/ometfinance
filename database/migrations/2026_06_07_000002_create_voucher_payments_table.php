<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVoucherPaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('voucher_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();

            // Links to the rows this payment posted, so reversal is clean.
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->foreignId('project_expense_id')->nullable()->constrained('project_expenses')->nullOnDelete();

            $table->date('paid_on');
            $table->string('amount');                 // encrypted decimal-as-string
            $table->string('mode')->nullable();
            $table->string('check_no')->nullable();
            $table->date('check_date')->nullable();
            $table->text('notes')->nullable();        // encrypted

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('voucher_payments');
    }
}
