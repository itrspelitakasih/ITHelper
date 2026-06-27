<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class Periksa extends Model
{
    protected $connection = 'lis';
    protected $table = 'periksa';
    protected $guarded = [];
    public $timestamps = false;

    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'id_pasien');
    }

    public function dokter()
    {
        return $this->belongsTo(Dokter::class, 'id_dokter');
    }

    public function dokter2()
    {
        return $this->belongsTo(Dokter::class, 'id_dokter2');
    }

    public function ruang()
    {
        return $this->belongsTo(Ruang::class, 'id_ruang');
    }

    public function petugas()
    {
        return $this->belongsTo(Petugas::class, 'id_petugas');
    }

    public static function getSeq()
    {
        $today = date("Y-m-d");
        $maxSeq = self::whereDate('tanggal', $today)->max('seq');
        $noUrut = (int)$maxSeq;
        $noUrut++;
        return sprintf("%04d", $noUrut);
    }
}
