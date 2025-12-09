<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PemeriksaanLab extends Model
{
    use HasFactory;

    protected $table = 'kk_pemeriksaan_lab';
    public $timestamps = false;
    protected $guarded = [
        'id',
    ];

    public function value_codeable_concept1_data()
    {
        return $this->belongsTo(LabResultValueCodeable::class, 'value_codeable_concept1', 'id');
    }

    public function value_codeable_concept2_data()
    {
        return $this->belongsTo(LabResultValueCodeable::class, 'value_codeable_concept2', 'id');
    }

    public function PemeriksaanTambahanLab()
    {
        return $this->belongsTo(PemeriksaanTambahanLab::class, 'id_pemeriksaan_lab', 'id');
    }

    public function DiagnosticReportCategory()
    {
        return $this->belongsTo(DiagnosticReportCategory::class, 'diagnostic_report_category_id', 'id');
    }

    public function SatusehatSatuan()
    {
        return $this->belongsTo(SatusehatSatuan::class, 'satuan_satusehat_id', 'id');
    }
}
