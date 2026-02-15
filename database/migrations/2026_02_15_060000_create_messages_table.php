<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->char('ip_hash', 64);
            $table->string('category', 50)->nullable();
            $table->integer('likes_count')->default(0);
            $table->timestamps();
            $table->timestamp('expires_at')->nullable();

            $table->index('created_at');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
