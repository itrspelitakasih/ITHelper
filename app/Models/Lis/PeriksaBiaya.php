<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class PeriksaBiaya extends Model
{
    protected $connection = 'lis';
    protected $table = 'periksa_biaya';
    protected $guarded = [];
    public $timestamps = false;
}
