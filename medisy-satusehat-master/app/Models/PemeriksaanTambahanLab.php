<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PemeriksaanTambahanLab extends Model
{
    use HasFactory;

    protected $table = 'kk_pemeriksaan_tambahan_lab';
    public $timestamps = false;

    protected $fillable = [
        'id_pemeriksaan_lab',
        'id_kunjungan',
        'status',
        'biaya',
        'ucode',
        'id_perusahaan',
        'created',
        'updated',
        'ket',
        'deleted',
        'petugas',
        'hasil',
        'keterangan',
        'jam_ambil_sample',
        'jam_selesai',
        'diagnosticreport_code',
        'diagnosticreport_display',
        'diagnosticreport_conclusion_code'
    ];

    public function Petugas()
    {
        return $this->belongsTo(User::class, 'petugas', 'id');
    }

    public function PemeriksaanLab()
    {
        return $this->belongsTo(PemeriksaanLab::class, 'id_pemeriksaan_lab', 'id');
    }

    public function SampelLab()
    {
        return $this->belongsTo(SampelLab::class, 'id_sampel', 'id');
    }

    public function DiagnosticReportConclusion()
    {
        return $this->belongsTo(DiagnosticReportConclusion::class, 'diagnosticreport_conclusion_code', 'code');
    }
}
