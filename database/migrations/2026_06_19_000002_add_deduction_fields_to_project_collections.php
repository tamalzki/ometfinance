<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statutory/contractual deductions taken out of a client collection before
 * it actually hits the bank — VAT, withholding tax, retention, recoupment,
 * and any other agency-specific deduction. Rates are stored alongside the
 * computed amount so a row stays self-explanatory even if rates change
 * later. Amount columns are TEXT to match the existing `encrypted` cast
 * pattern used for other financial columns on this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_collections', function (Blueprint $table) {
            $table->string('client_type', 20)->nullable()->after('amount');       // government | private
            $table->string('transaction_type', 20)->nullable()->after('client_type'); // goods | services

            $table->decimal('vat_rate', 5, 2)->nullable()->after('transaction_type');
            $table->text('vat_amount')->nullable()->after('vat_rate');

            $table->decimal('wht_rate', 5, 2)->nullable()->after('vat_amount');
            $table->text('wht_amount')->nullable()->after('wht_rate');

            $table->decimal('retention_rate', 5, 2)->nullable()->after('wht_amount');
            $table->text('retention_amount')->nullable()->after('retention_rate');

            $table->decimal('recoupment_rate', 5, 2)->nullable()->after('retention_amount');
            $table->text('recoupment_amount')->nullable()->after('recoupment_rate');

            $table->text('other_deductions_amount')->nullable()->after('recoupment_amount');
            $table->text('other_deductions_notes')->nullable()->after('other_deductions_amount');
        });
    }

    public function down(): void
    {
        Schema::table('project_collections', function (Blueprint $table) {
            $table->dropColumn([
                'client_type', 'transaction_type',
                'vat_rate', 'vat_amount',
                'wht_rate', 'wht_amount',
                'retention_rate', 'retention_amount',
                'recoupment_rate', 'recoupment_amount',
                'other_deductions_amount', 'other_deductions_notes',
            ]);
        });
    }
};
