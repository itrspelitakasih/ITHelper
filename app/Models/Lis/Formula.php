<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class Formula extends Model
{
    protected $connection = 'lis';
    protected $table = 'formula';
    protected $guarded = [];
    public $timestamps = false;
}
