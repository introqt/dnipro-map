<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('points')) {
            Schema::table('points', function (Blueprint $table) {
                if (! Schema::hasColumn('points', 'type')) {
                    $table->string('type')->default('static_danger')->after('photo_url');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('points')) {
            Schema::table('points', function (Blueprint $table) {
                if (Schema::hasColumn('points', 'type')) {
                    $table->dropColumn('type');
                }
            });
        }
    }
};
