<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class PeriksaSimrsDetail extends Model
{
    protected $connection = 'lis';
    protected $table = 'periksa_simrs_detail';
    protected $guarded = [];
    public $timestamps = false;
}
