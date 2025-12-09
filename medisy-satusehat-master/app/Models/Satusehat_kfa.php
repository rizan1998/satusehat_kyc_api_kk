<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Satusehat_kfa extends Model
{
    use HasFactory;
    protected $table = 'satusehat_kfa';
    public $timestamps = false;

    public function obat()
    {
        return $this->hasMany(Obat::class, 'kode_kfa', 'kode_kfa_pa');
    }
}
