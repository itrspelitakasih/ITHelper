<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExternalDatabaseManager
{
    public function setting(): ?ExternalDatabaseSetting
    {
        return ExternalDatabaseSetting::query()->where('is_active', true)->latest()->first();
    }

    public function connection(): Connection
    {
        $setting = $this->setting();

        if (! $setting) {
            throw new RuntimeException('Koneksi database eksternal belum dikonfigurasi.');
        }

        return $this->connect($this->configuration($setting));
    }

    public function antaraConnection(): Connection
    {
        $setting = $this->setting();

        if (! $setting) {
            throw new RuntimeException('Koneksi database eksternal belum dikonfigurasi.');
        }

        if (empty($setting->lis_antara_db_host)) {
            return $this->connection();
        }

        return $this->connect($this->antaraConfiguration($setting), 'lis_antara');
    }

    public function antaraConfiguration(ExternalDatabaseSetting|array $setting): array
    {
        $value = fn (string $key, mixed $default = null) => is_array($setting)
            ? ($setting[$key] ?? $default)
            : ($setting->{$key} ?? $default);

        return [
            'driver' => 'mysql',
            'host' => $value('lis_antara_db_host'),
            'port' => $value('lis_antara_db_port', 3306),
            'database' => $value('lis_antara_db_database'),
            'username' => $value('lis_antara_db_username'),
            'password' => $value('lis_antara_db_password', ''),
            'unix_socket' => '',
            'charset' => 'utf8',
            'collation' => 'utf8_general_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => [],
        ];
    }

    public function test(array $configuration): void
    {
        $connection = $this->connect($configuration, 'external_test');
        $connection->select('select 1');
        DB::purge('external_test');
    }

    public function configuration(ExternalDatabaseSetting|array $setting): array
    {
        $value = fn (string $key, mixed $default = null) => is_array($setting)
            ? ($setting[$key] ?? $default)
            : ($setting->{$key} ?? $default);

        return [
            'driver' => $value('driver', 'mysql'),
            'host' => $value('host'),
            'port' => $value('port', 3306),
            'database' => $value('database'),
            'username' => $value('username'),
            'password' => $value('password', ''),
            'unix_socket' => '',
            'charset' => $value('charset', 'latin1'),
            'collation' => $value('charset', 'latin1') === 'latin1' ? 'latin1_swedish_ci' : 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => [],
        ];
    }

    private function connect(array $configuration, string $name = 'external'): Connection
    {
        Config::set("database.connections.{$name}", $configuration);
        DB::purge($name);

        return DB::connection($name);
    }
}
