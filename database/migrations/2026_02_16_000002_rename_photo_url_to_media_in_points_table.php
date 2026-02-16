<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('points', function (Blueprint $table) {
            $table->renameColumn('photo_url', 'media');
        });
    }

    public function down(): void
    {
        Schema::table('points', function (Blueprint $table) {
            $table->renameColumn('media', 'photo_url');
        });
    }
};
