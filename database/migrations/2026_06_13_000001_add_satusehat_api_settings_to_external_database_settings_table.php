<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_database_settings', function (Blueprint $table) {
            $table->text('satusehat_client_id')->nullable();
            $table->text('satusehat_client_secret')->nullable();
            $table->string('satusehat_organization_id')->nullable();
            $table->string('satusehat_auth_url')->nullable();
            $table->string('satusehat_fhir_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('external_database_settings', function (Blueprint $table) {
            $table->dropColumn([
                'satusehat_client_id',
                'satusehat_client_secret',
                'satusehat_organization_id',
                'satusehat_auth_url',
                'satusehat_fhir_url',
            ]);
        });
    }
};
