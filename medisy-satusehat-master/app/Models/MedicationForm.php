<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicationForm extends Model
{
    use HasFactory;

    protected $table = 'satusehat_medication_form';
    public $timestamps = false;

    public function resep_obat()
    {
        return $this->hasMany(ResepObat::class, 'medication_form_code', 'code');
    }
}
