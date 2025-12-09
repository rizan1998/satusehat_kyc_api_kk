<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Racik extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'kk_racik';
    protected $guarded = ['id'];

    public function obat()
    {
        return $this->hasMany(RacikObat::class, 'id_racik', 'id');
    }

    public function satusehat_medication_form()
    {
        return $this->belongsTo(Satusehat_medication_form::class, 'medication_form_code', 'code');
    }

    public function route()
    {
        return $this->belongsTo(SatusehatRoute::class, 'satusehat_route_id', 'id');
    }

    public function satuan_obat()
    {
        return $this->belongsTo(ObatSatuan::class, 'satuan', 'id_satuan');
    }

    // FIXME: Change this, use satuan_obat instead!
    public function satuan_dosis()
    {
        return $this->belongsTo(ObatSatuan::class, 'id_satuan_dosis', 'id_satuan');
    }

    public function satusehat_satuan()
    {
        return $this->belongsTo(SatusehatSatuan::class, 'signa_period', 'code');
    }

    public function bentuk_sediaan()
    {
        return $this->belongsTo(Satusehat_bentuk_sediaan::class, 'id_satuan_dosis', 'id');
    }

    // public function detail_pembayaran()
    // {
    //     return $this->belongsTo(DetailPembayaran::class, 'id_obat', 'id_obat')->where('id_kunjungan', $this->id_kunjungan);
    // }


    // public function obat()
    // {
    //     return $this->hasMany(RacikObat::class, 'id_racik', 'id');
    // }

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
