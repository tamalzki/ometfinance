<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVoucherRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('voucher_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // create | edit | delete
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->foreignId('requested_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->json('payload')->nullable();
            $table->json('entries_payload')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('voucher_requests');
    }
}
