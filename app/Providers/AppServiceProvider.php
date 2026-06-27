<?php

namespace App\Providers;

use App\Models\ApplicationSetting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('external_database_settings')) {
                $setting = \App\Models\ExternalDatabaseSetting::query()->latest()->first();
                if ($setting && $setting->lis_db_host) {
                    config([
                        'database.connections.lis' => [
                            'driver' => 'mysql',
                            'host' => $setting->lis_db_host,
                            'port' => $setting->lis_db_port ?? 3306,
                            'database' => $setting->lis_db_database,
                            'username' => $setting->lis_db_username,
                            'password' => $setting->lis_db_password,
                            'charset' => 'utf8',
                            'collation' => 'utf8_general_ci',
                            'prefix' => '',
                            'strict' => false,
                        ]
                    ]);
                }
                if ($setting && $setting->lis_antara_db_host) {
                    config([
                        'database.connections.lis_antara' => [
                            'driver' => 'mysql',
                            'host' => $setting->lis_antara_db_host,
                            'port' => $setting->lis_antara_db_port ?? 3306,
                            'database' => $setting->lis_antara_db_database,
                            'username' => $setting->lis_antara_db_username,
                            'password' => $setting->lis_antara_db_password,
                            'charset' => 'utf8',
                            'collation' => 'utf8_general_ci',
                            'prefix' => '',
                            'strict' => false,
                        ]
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Ignore during setup/migrations
        }

        View::composer('*', function ($view) {
            $view->with('appName', ApplicationSetting::appName());
            $view->with('appLogoUrl', ApplicationSetting::logoUrl());
        });
    }
}
