<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class Kode extends Model
{
    protected $connection = 'lis';
    protected $table = 'kode';
    protected $guarded = [];
    public $timestamps = false;
}
