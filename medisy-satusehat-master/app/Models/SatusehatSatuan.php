<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SatusehatSatuan extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'satusehat_satuan';

    protected $guarded = [
        'id',
    ];

    public function PemeriksaanLab()
    {
        return $this->belongsTo(PemeriksaanLab::class, 'satuan_satusehat_id', 'id');
    }
}
