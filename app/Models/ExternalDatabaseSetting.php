<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalDatabaseSetting extends Model
{
    protected $fillable = [
        'name', 'driver', 'host', 'port', 'database', 'username', 'password',
        'charset', 'is_active', 'last_tested_at',
        'satusehat_client_id', 'satusehat_client_secret', 'satusehat_organization_id',
        'satusehat_auth_url', 'satusehat_fhir_url', 'lis_url',
        'lis_db_host', 'lis_db_port', 'lis_db_database', 'lis_db_username', 'lis_db_password',
        'lis_antara_db_host', 'lis_antara_db_port', 'lis_antara_db_database', 'lis_antara_db_username', 'lis_antara_db_password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'satusehat_client_id' => 'encrypted',
            'satusehat_client_secret' => 'encrypted',
            'lis_db_password' => 'encrypted',
            'lis_antara_db_password' => 'encrypted',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }
}
