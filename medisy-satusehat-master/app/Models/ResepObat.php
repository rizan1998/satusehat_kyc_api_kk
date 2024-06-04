<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResepObat extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'kk_resep_obat',
        $fillable = ['satusehat_status'];

    public function obat()
    {
        return $this->belongsTo(Obat::class, 'id_obat', 'id');
    }

    public function medication_form()
    {
        return $this->belongsTo(MedicationForm::class, 'medication_form_code', 'code');
    }

    public function route()
    {
        return $this->belongsTo(SatusehatRoute::class, 'satusehat_route_code', 'code');
    }

    public function satuan_dosis()
    {
        return $this->belongsTo(ObatSatuan::class, 'id_satuan_dosis', 'id_satuan');
    }
}
