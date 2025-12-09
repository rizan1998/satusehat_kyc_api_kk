<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriObat extends Model
{
    use HasFactory;

    protected $table = 'kk_kategori_obat';

    const CREATED_AT = 'created',
        UPDATED_AT = 'updated',
        DELETED_AT = 'deleted';

    protected $fillable = [
        'id',
        'nama',
        'type',
        'order',
        'keterangan',
        'id_perusahaan',
        'user',
        'ket',
        'created',
        'updated',
        'deleted',
    ];
}
