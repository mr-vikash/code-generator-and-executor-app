<?php
// database/migrations/2024_01_01_000000_create_code_histories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_histories', function (Blueprint $table) {
            $table->id();
            $table->text('prompt');
            $table->longText('code');
            $table->string('type', 50);
            $table->text('description')->nullable();
            $table->text('libraries')->nullable();
            $table->timestamps();
            
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_histories');
    }
};