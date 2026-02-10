<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscriptions')->truncate();
    }

    public function down(): void
    {
        // Cannot restore truncated data
    }
};
