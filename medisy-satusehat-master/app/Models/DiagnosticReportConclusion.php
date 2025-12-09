<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticReportConclusion extends Model
{
    use HasFactory;
    protected $table = 'diagnostic_report_conclusions';
    public $timestamps = false;

    public function PemeriksaanTambahanLab()
    {
        return $this->belongsTo(PemeriksaanTambahanLab::class, 'diagnosticreport_conclusion_code', 'code');
    }
}
