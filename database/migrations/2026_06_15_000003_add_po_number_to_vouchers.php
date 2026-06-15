<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPoNumberToVouchers extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Column C — PO Number, distinct from the "Type" dropdown that was
            // normalized from this same spreadsheet column.
            $table->text('po_number')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('po_number');
        });
    }
}
