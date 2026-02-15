<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false)->after('likes_count');
            $table->index('is_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['is_deleted']);
            $table->dropColumn('is_deleted');
        });
    }
};
