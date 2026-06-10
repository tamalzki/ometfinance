<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVouchersTable extends Migration
{
    public function up()
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no')->unique();

            // Voucher date = when recorded/approved. Release date = when money
            // actually left (kept separate per the business requirement).
            $table->date('voucher_date');
            $table->date('due_date')->nullable();
            $table->date('release_date')->nullable();

            $table->string('payee_name');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            // Default source account the disbursement is expected to draw from.
            $table->foreignId('source_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();

            $table->string('transaction_type')->nullable();
            $table->string('reference')->nullable();          // encrypted at model
            $table->string('amount_payable');                  // encrypted decimal-as-string
            $table->string('mode_of_payment')->nullable();
            $table->enum('status', ['draft', 'unpaid', 'partial', 'pdc', 'paid', 'cancelled'])->default('unpaid');

            $table->text('particular')->nullable();            // encrypted
            $table->text('notes')->nullable();                 // encrypted

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('due_date');
            $table->index('voucher_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('vouchers');
    }
}
