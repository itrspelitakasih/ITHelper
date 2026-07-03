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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->nullable()->index();
            $table->string('device_id')->nullable();
            $table->string('session_id')->nullable();
            $table->string('phone')->index();
            $table->string('name')->nullable();
            $table->text('message');
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $table->string('status')->default('pending'); // pending, sent, delivered, read, failed
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
