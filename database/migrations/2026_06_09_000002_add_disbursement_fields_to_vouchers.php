<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDisbursementFieldsToVouchers extends Migration
{
    public function up()
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Column O — free-text remarks separate from the internal notes field
            $table->text('remarks')->nullable()->after('particular');
            // Column T — description of the funding source (e.g. "from Jan 1 encashment")
            $table->text('source_of_fund')->nullable()->after('remarks');
            // Column U — official receipt, credit memo, sales invoice, or charge invoice reference
            $table->string('or_ref', 255)->nullable()->after('source_of_fund');
            // Column V — change / excess amount returned to the company
            $table->decimal('change_amount', 15, 2)->nullable()->after('or_ref');
        });
    }

    public function down()
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['remarks', 'source_of_fund', 'or_ref', 'change_amount']);
        });
    }
}
