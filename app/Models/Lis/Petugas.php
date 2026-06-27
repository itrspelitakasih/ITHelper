<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class Petugas extends Model
{
    protected $connection = 'lis';
    protected $table = 'petugas';
    protected $guarded = [];
    public $timestamps = false;
}
