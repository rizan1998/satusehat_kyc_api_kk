<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AntrianMasukRuangan extends Model
{
    use HasFactory;

    protected $table = 'kk_antrian_masuk_ruangan';
    public $timestamps = false;

    const CREATED_AT = 'created',
        UPDATED_AT = 'updated',
        DELETED_AT = 'deleted';

    const KETERANGAN_ANAMNESA = 'ANAMNESA',
        KETERANGAN_DOKTER = 'DOKTER';
}
