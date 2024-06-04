<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisTindakan extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'kk_jenis_tindakan';
}
