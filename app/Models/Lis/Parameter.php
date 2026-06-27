<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class Parameter extends Model
{
    protected $connection = 'lis';
    protected $table = 'parameter';
    protected $guarded = [];
    public $timestamps = false;
}
