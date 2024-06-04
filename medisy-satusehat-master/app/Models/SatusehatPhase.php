<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SatusehatPhase extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'satusehat_phase',
        $fillable = [
            'id',
            'id_encounter',
            'allergy',
            'observation_pemeriksaan_fisik',
            'service_request',
            'specimen',
            'observation_hasil_pemeriksaan_penunjang',
            'diagnostic_report',
            'condition_diagnosis',
            'procedure_medis',
            'composition_diet',
            'procedure_edukasi',
            'medication',
            'clinical_impression',
            'service_request_tindak_lanjut'
        ];
}
