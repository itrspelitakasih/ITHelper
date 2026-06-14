<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalDatabaseSetting extends Model
{
    protected $fillable = [
        'name', 'driver', 'host', 'port', 'database', 'username', 'password',
        'charset', 'is_active', 'last_tested_at',
        'satusehat_client_id', 'satusehat_client_secret', 'satusehat_organization_id',
        'satusehat_auth_url', 'satusehat_fhir_url',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'satusehat_client_id' => 'encrypted',
            'satusehat_client_secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }
}
