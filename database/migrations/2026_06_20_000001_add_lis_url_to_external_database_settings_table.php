<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_database_settings', function (Blueprint $table) {
            $table->string('lis_url')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('external_database_settings', function (Blueprint $table) {
            $table->dropColumn('lis_url');
        });
    }
};
