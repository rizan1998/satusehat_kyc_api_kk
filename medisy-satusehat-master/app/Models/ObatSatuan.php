<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObatSatuan extends Model
{
    use HasFactory;

    protected $table = 'kk_satuan';
    public $timestamps = false;

    public function obat()
    {
        return $this->hasMany(Obat::class, 'id_satuan', 'id_satuan');
    }

    public function racik_obat()
    {
        return $this->hasMany(Racik::class, 'satuan', 'satuan');
    }

    public function resep_obat()
    {
        return $this->hasMany(ResepObat::class, 'id_satuan_dosis', 'id');
    }
}
