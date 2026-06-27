<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => 'settings.app'],
                [
                    'name' => 'Kelola Aplikasi',
                    'group' => 'Pengaturan',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('slug', 'settings.app')->delete();
        }

        Schema::dropIfExists('application_settings');
    }
};
