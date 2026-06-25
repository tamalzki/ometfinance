<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceToAdminInvitesTable extends Migration
{
    public function up()
    {
        Schema::table('admin_invites', function (Blueprint $table) {
            $table->string('source')->nullable()->after('role');
        });
    }

    public function down()
    {
        Schema::table('admin_invites', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
}
