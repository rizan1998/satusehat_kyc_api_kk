<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Satusehat_medication_form extends Model
{
    protected $table = 'satusehat_medication_form';
    use HasFactory;
    public function obat()
    {
        return $this->hasMany(Obat::class, 'medication_form_code', 'code');
    }

    public function racik()
    {
        return $this->hasMany(Racik::class, 'medication_form_code', 'code');
    }
}
