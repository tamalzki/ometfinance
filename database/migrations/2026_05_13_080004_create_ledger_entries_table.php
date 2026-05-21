<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLedgerEntriesTable extends Migration
{
    public function up()
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();
            $table->date('date');
            $table->string('description');
            $table->decimal('amount_out', 15, 2)->nullable();
            $table->decimal('amount_in', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ledger_entries');
    }
}
