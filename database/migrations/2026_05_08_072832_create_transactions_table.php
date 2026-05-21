<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->enum('category', ['revenue', 'expense', 'payroll', 'material', 'subcontractor', 'overhead']);
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->enum('status', ['pending', 'approved', 'paid'])->default('pending');
            $table->string('reference_no')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
