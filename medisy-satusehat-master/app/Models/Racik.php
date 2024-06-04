<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Racik extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'kk_racik',
        $fillable = ['satusehat_status'];

    public function obat()
    {
        return $this->hasMany(RacikObat::class, 'id_racik', 'id');
    }

    // public function medication_form()
    // {
    //     return $this->belongsTo(MedicationForm::class, 'medication_form_code', 'code');
    // }

    // public function route()
    // {
    //     return $this->belongsTo(SatusehatRoute::class, 'satusehat_route_code', 'code');
    // }

    // public function satuan_obat()
    // {
    //     return $this->belongsTo(ObatSatuan::class, 'satuan', 'id_satuan');
    // }

    // public function satuan_dosis()
    // {
    //     return $this->belongsTo(ObatSatuan::class, 'id_satuan_dosis', 'id_satuan');
    // }
}
