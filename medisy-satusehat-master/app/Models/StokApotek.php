<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StokApotek extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'kk_stok_apotek';

    public function satuan()
    {
        return $this->belongsTo(ObatSatuan::class, 'id_satuan', 'id_satuan');
    }

    // public function sumberStok()
    // {
    //     return $this->belongsTo(SumberStok::class, 'sumber_stok', 'id');
    // }
}
