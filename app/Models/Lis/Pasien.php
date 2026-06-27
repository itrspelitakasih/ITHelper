<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class Pasien extends Model
{
    protected $connection = 'lis';
    protected $table = 'pasien';
    protected $guarded = [];
    public $timestamps = false;
}
