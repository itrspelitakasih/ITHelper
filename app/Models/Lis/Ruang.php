<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class Ruang extends Model
{
    protected $connection = 'lis';
    protected $table = 'ruang';
    protected $guarded = [];
    public $timestamps = false;
}
