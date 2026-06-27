<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class PaketDetail extends Model
{
    protected $connection = 'lis';
    protected $table = 'paket_detail';
    protected $guarded = [];
    public $timestamps = false;
}
