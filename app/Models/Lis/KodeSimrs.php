<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class KodeSimrs extends Model
{
    protected $connection = 'lis';
    protected $table = 'kode_simrs';
    protected $guarded = [];
    public $timestamps = false;
}
