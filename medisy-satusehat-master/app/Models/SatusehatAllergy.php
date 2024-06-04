<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SatusehatAllergy extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'satusehat_allergy';

    public function CatatanPasien()
    {
        return $this->hasMany(CatatanPasien::class, 'satusehat_code', 'code');
    }
}
