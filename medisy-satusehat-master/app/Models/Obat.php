<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Obat extends Model
{
    use HasFactory;

    protected $table = 'kk_obat';
    public $timestamps = false;

    public function reset_obat()
    {
        return $this->hasMany(ResepObat::class, 'id_obat', 'id');
    }

    public function racik_obat()
    {
        return $this->hasMany(RacikObat::class, 'id_obat', 'id');
    }

    public function satuan()
    {
        return $this->belongsTo(ObatSatuan::class, 'id_satuan', 'id_satuan');
    }

    public function satuan_dosis()
    {
        return $this->belongsTo(ObatSatuan::class, 'id_satuan_dosis', 'id_satuan');
    }
}
