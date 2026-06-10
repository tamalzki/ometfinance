<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChangeOrRefToTextOnVouchers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE vouchers MODIFY COLUMN or_ref TEXT NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE vouchers MODIFY COLUMN or_ref VARCHAR(255) NULL');
    }
}
