<?php

// namespace App\Models;

// use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\Model;

// class Obat extends Model
// {
//     use HasFactory;

//     protected $table = 'kk_obat';
//     public $timestamps = false;

//     public function reset_obat()
//     {
//         return $this->hasMany(ResepObat::class, 'id_obat', 'id');
//     }

//     public function racik_obat()
//     {
//         return $this->hasMany(RacikObat::class, 'id_obat', 'id');
//     }

//     public function satuan()
//     {
//         return $this->belongsTo(ObatSatuan::class, 'id_satuan', 'id_satuan');
//     }

//     public function satuan_dosis()
//     {
//         return $this->belongsTo(ObatSatuan::class, 'id_satuan_dosis', 'id_satuan');
//     }
// }


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Obat extends Model
{
    use HasFactory;

    protected $table = 'kk_obat';
    public $timestamps = false;

    public function golongan_obat()
    {
        return $this->belongsTo(GolonganObat::class, 'id_golongan', 'id');
    }

    public function kategori_obat()
    {
        return $this->belongsTo(KategoriObat::class, 'id_kategori', 'id');
    }

    public function stok_apotek()
    {
        return $this->hasMany(StokApotek::class, 'id_obat', 'id');
    }

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

    public function satusehat_kfa()
    {
        return $this->belongsTo(Satusehat_kfa::class, 'kode_kfa', 'kode_kfa_pa');
    }

    public function satusehat_medication_form()
    {
        return $this->belongsTo(Satusehat_medication_form::class, 'medication_form_code', 'code');
    }
}
