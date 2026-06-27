<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class Dokter extends Model
{
    protected $connection = 'lis';
    protected $table = 'dokter';
    protected $guarded = [];
    public $timestamps = false;
}
