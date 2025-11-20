<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticReportCategory extends Model
{
    use HasFactory;

    protected $table = 'diagnostic_report_categories';
    public $timestamps = false;

    public function PemeriksaanLab()
    {
        return $this->belongsTo(PemeriksaanLab::class, 'id_kategori_layanan', 'id');
    }
}
