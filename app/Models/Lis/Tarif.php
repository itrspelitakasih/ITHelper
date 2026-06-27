<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class Tarif extends Model
{
    protected $connection = 'lis';
    protected $table = 'tarif';
    protected $guarded = [];
    public $timestamps = false;

    /**
     * Map 'nama' to 'pemeriksaan' column.
     */
    public function getNamaAttribute()
    {
        return $this->attributes['pemeriksaan'] ?? null;
    }

    /**
     * Set 'nama' mapped to 'pemeriksaan' column.
     */
    public function setNamaAttribute($value)
    {
        $this->attributes['pemeriksaan'] = $value;
        unset($this->attributes['nama']);
    }

    /**
     * Map 'biaya' to 'tarif' column.
     */
    public function getBiayaAttribute()
    {
        return $this->attributes['tarif'] ?? null;
    }

    /**
     * Set 'biaya' mapped to 'tarif' column.
     */
    public function setBiayaAttribute($value)
    {
        $this->attributes['tarif'] = $value;
        unset($this->attributes['biaya']);
    }
}

