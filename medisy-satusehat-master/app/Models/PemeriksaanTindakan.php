<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PemeriksaanTindakan extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'kk_pemeriksaan_tindakan',
        $fillable = ['satusehat_status'];

    public function icd9()
    {
        return $this->belongsTo(Icd9::class, 'id_icd9', 'id');
    }
}
