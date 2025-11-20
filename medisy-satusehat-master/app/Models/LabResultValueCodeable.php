<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabResultValueCodeable extends Model
{
    use HasFactory;

    protected $table = 'lab_result_value_codeable';
    public $timestamps = false;

    public function pemeriksaanLab1()
    {
        return $this->hasMany(PemeriksaanLab::class, 'value_codeable_concept1', 'id');
    }

    public function pemeriksaanLab2()
    {
        return $this->hasMany(PemeriksaanLab::class, 'value_codeable_concept2', 'id');
    }

    // Method bantu untuk menggabungkan keduanya
    public function pemeriksaanLabs()
    {
        return $this->pemeriksaanLab1->merge($this->pemeriksaanLab2);
    }
}
