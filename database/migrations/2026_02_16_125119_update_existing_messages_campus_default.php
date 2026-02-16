<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Set a default campus for existing messages that have NULL campus
        DB::table('messages')
            ->whereNull('campus')
            ->update(['campus' => 'Main Campus']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this data update as it's just filling in NULLs
    }
};
