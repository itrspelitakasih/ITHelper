<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $connection = 'lis';
    protected $table = 'log';
    protected $guarded = [];
    public $timestamps = false;
}
