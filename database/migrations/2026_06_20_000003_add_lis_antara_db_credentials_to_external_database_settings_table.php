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
        Schema::table('external_database_settings', function (Blueprint $table) {
            $table->string('lis_antara_db_host')->nullable();
            $table->integer('lis_antara_db_port')->nullable();
            $table->string('lis_antara_db_database')->nullable();
            $table->string('lis_antara_db_username')->nullable();
            $table->string('lis_antara_db_password')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('external_database_settings', function (Blueprint $table) {
            $table->dropColumn([
                'lis_antara_db_host',
                'lis_antara_db_port',
                'lis_antara_db_database',
                'lis_antara_db_username',
                'lis_antara_db_password',
            ]);
        });
    }
};
