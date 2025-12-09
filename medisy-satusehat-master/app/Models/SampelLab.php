<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SampelLab extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'kk_sampel_lab';

    public function Snomed()
    {
        return $this->belongsTo(SnomedCT::class, 'code', 'conceptId');
    }
}
