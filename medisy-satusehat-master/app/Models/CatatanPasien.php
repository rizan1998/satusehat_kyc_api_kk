<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatatanPasien extends Model
{
    use HasFactory;

    protected $table = 'kk_catatan_pasien',
        $fillable = ['satusehat_status'];

    public $timestamps = false;

    const JENIS = [
        'alergi-obat' => 'medication',
        'alergi-makanan' => 'food',
        'alergi-lingkungan' => 'environment',
        'alergi-biologis' => 'biologic',
    ];

    public function SatusehatAllergy()
    {
        return $this->belongsTo(SatusehatAllergy::class, 'satusehat_code', 'code');
    }
}
