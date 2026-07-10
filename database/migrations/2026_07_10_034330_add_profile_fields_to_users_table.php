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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->text('bio')->nullable()->after('phone');
            $table->string('facebook')->nullable()->after('bio');
            $table->string('twitter')->nullable()->after('facebook');
            $table->string('linkedin')->nullable()->after('twitter');
            $table->string('instagram')->nullable()->after('linkedin');
            $table->string('country')->nullable()->after('instagram');
            $table->string('city_state')->nullable()->after('country');
            $table->string('postal_code')->nullable()->after('city_state');
            $table->string('tax_id')->nullable()->after('postal_code');
            $table->string('avatar')->nullable()->after('tax_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'bio',
                'facebook',
                'twitter',
                'linkedin',
                'instagram',
                'country',
                'city_state',
                'postal_code',
                'tax_id',
                'avatar',
            ]);
        });
    }
};
