<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RacikObat extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'kk_racik_obat';

    public function racik()
    {
        return $this->belongsTo(Racik::class, 'id_racik', 'id');
    }

    // public function obat()
    // {
    //     return $this->belongsTo(Obat::class, 'id_obat', 'id');
    // }
}
