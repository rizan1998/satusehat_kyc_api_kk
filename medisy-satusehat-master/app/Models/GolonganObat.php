<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GolonganObat extends Model
{
    use HasFactory;

    protected $table = 'kk_golongan_obat';

    const CREATED_AT = 'created',
        UPDATED_AT = 'updated',
        DELETED_AT = 'deleted';

    protected $fillable = [
        'id',
        'nama',
        'singkatan',
        'keterangan',
        'hash_id',
        'id_user',
        'id_perusahaan',
        'ket',
        'created',
        'updated',
        'deleted',
        'user',
    ];
}
