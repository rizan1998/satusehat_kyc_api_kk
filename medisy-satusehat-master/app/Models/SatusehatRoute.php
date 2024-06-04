<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SatusehatRoute extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'satusehat_route';

    public function resep_obat()
    {
        return $this->hasMany(ResepObat::class, 'satusehat_route_code', 'code');
    }
}
