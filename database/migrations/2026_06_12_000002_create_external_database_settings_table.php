<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_database_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('SIMRS Khanza');
            $table->string('driver')->default('mysql');
            $table->string('host')->default('127.0.0.1');
            $table->unsignedSmallInteger('port')->default(3306);
            $table->string('database');
            $table->string('username');
            $table->text('password')->nullable();
            $table->string('charset')->default('latin1');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_database_settings');
    }
};
