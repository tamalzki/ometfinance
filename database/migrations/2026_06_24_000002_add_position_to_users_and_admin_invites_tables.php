<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPositionToUsersAndAdminInvitesTables extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Free-text override for the role-based default label (e.g. a
            // specific accounting user titled "Accounting Head" instead of
            // the generic "Accounting", without changing their permissions).
            $table->string('position')->nullable()->after('source');
        });

        Schema::table('admin_invites', function (Blueprint $table) {
            $table->string('position')->nullable()->after('source');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('position');
        });

        Schema::table('admin_invites', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
}
