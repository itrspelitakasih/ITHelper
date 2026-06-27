<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $connection = 'lis';
    protected $table = 'result';
    protected $guarded = [];
    public $timestamps = false;
}
