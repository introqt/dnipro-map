<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('points', function (Blueprint $table) {
            $table->json('photo_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('points', function (Blueprint $table) {
            $table->string('photo_url')->nullable()->change();
        });
    }
};
