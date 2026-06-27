<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class Paket extends Model
{
    protected $connection = 'lis';
    protected $table = 'paket';
    protected $guarded = [];
    public $timestamps = false;
}
