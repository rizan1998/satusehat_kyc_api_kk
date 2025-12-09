<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResepObat extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'kk_resep_obat',
        $fillable = [
            'signa1',
            'signa2',
            'signa_period',
            'medication_form_code',
            'id_kunjungan',
            'id_obat',
            'qty',
            'catatan',
            'signa_desc',
            'tanggal_diberikan',
            'waktu_diberikan',
            'user',
            'id_perusahaan',
            'status',
            'created',
            'satusehat_route_id',
        ];

    public function obat()
    {
        return $this->belongsTo(Obat::class, 'id_obat', 'id');
    }

    public function route()
    {
        return $this->belongsTo(SatusehatRoute::class, 'satusehat_route_id', 'id');
    }

    public function satuan_dosis()
    {
        return $this->belongsTo(ObatSatuan::class, 'id_satuan_dosis', 'id_satuan');
    }

    // public function detail_pembayaran()
    // {
    //     return $this->hasOne(DetailPembayaran::class, 'id_obat', 'id_obat')->where('id_kunjungan', $this->id_kunjungan);
    // }

    // public function incrementStockObatResep()
    // {
    //     $qty_resep = $this->db->from('kk_qty_resep_obat')->where(['id_resep' => $this->id])->get()->result_array();
    //     foreach ($qty_resep as $key => $value) {
    //         $stok = $this->sistem_model->_get_where_id('kk_stok_apotek', ['id_obat' => $this->id_obat, 'sumber_stok' => $value['id_sumber']]);
    //         $stok_update = $value['qty'] + $stok['stok'];
    //         $this->db->update('kk_stok_apotek', ['stok' => $stok_update], ['id_obat' => $this->id_obat, 'sumber_stok' => $value['id_sumber']]);
    //     }
    //     $this->db->delete('kk_qty_resep_obat', ['id_resep' => $this->id]);
    //     $this->db->delete('kk_kartu_stok', ['id_resep_obat' => $this->id]);
    // }
}
