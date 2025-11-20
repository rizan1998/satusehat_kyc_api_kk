<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Satusehat_bentuk_sediaan extends Model
{
    use HasFactory;
    protected $table = 'satusehat_bentuk_sediaan';
    public $timestamps = false;

    public function racik()
    {
        return $this->hasMany(Racik::class, 'id_satuan_dosis', 'id');
    }
}
